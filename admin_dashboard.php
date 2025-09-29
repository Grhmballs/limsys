<?php
/**
 * LIMSys Admin Dashboard
 * Administrative interface for managing users and documents
 */

session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once 'db.php';

$message = '';
$message_type = '';
$search_query = '';

// Get pending password reset count for badge
$pending_resets_count = 0;
try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM password_resets WHERE status = 'pending'");
    $count_stmt->execute();
    $pending_resets_count = $count_stmt->fetchColumn();
} catch (PDOException $e) {
    // Silently fail if table doesn't exist yet
}

// Handle search functionality
if (isset($_GET['search'])) {
    $search_query = trim($_GET['search']);
}

// Handle user role change
if (isset($_POST['change_role'])) {
    $user_id = (int)$_POST['user_id'];
    $new_role = $_POST['new_role'];
    
    if (in_array($new_role, ['admin', 'user', 'guest']) && $user_id !== $_SESSION['user_id']) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            if ($stmt->execute([$new_role, $user_id])) {
                $message = "User role updated successfully.";
                $message_type = "success";
            }
        } catch (PDOException $e) {
            $message = "Error updating user role.";
            $message_type = "danger";
        }
    } else {
        $message = "Invalid role or cannot change your own role.";
        $message_type = "warning";
    }
}

// Handle user deletion
if (isset($_POST['delete_user'])) {
    $user_id = (int)$_POST['user_id'];
    
    if ($user_id !== $_SESSION['user_id']) {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            if ($stmt->execute([$user_id])) {
                $message = "User deleted successfully.";
                $message_type = "success";
            }
        } catch (PDOException $e) {
            $message = "Error deleting user.";
            $message_type = "danger";
        }
    } else {
        $message = "Cannot delete your own account.";
        $message_type = "warning";
    }
}

// Handle document deletion
if (isset($_POST['delete_document'])) {
    $document_id = (int)$_POST['document_id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM documents WHERE id = ?");
        if ($stmt->execute([$document_id])) {
            $message = "Document deleted successfully.";
            $message_type = "success";
        }
    } catch (PDOException $e) {
        $message = "Error deleting document.";
        $message_type = "danger";
    }
}

// Fetch all users
try {
    $users_stmt = $pdo->query("SELECT id, name, email, role, status, created_at FROM users ORDER BY created_at DESC");
    $users = $users_stmt->fetchAll();
} catch (PDOException $e) {
    $users = [];
}

// Fetch all documents with enhanced search filtering
try {
    if (!empty($search_query)) {
        // Clean and prepare search terms for flexible matching
        $clean_search = trim($search_query);
        $search_words = explode(' ', $clean_search);
        $search_words = array_filter($search_words); // Remove empty elements
        
        if (!empty($search_words)) {
            // Build flexible search conditions
            $title_conditions = [];
            $desc_conditions = [];
            $params = [];
            
            // Add exact phrase match (highest priority)
            $title_conditions[] = "LOWER(d.title) LIKE LOWER(?)";
            $desc_conditions[] = "LOWER(d.description) LIKE LOWER(?)";
            $params[] = '%' . $clean_search . '%';
            $params[] = '%' . $clean_search . '%';
            
            // Add individual word matches for flexibility
            foreach ($search_words as $word) {
                if (strlen($word) >= 2) { // Only include words with 2+ characters
                    $title_conditions[] = "LOWER(d.title) LIKE LOWER(?)";
                    $desc_conditions[] = "LOWER(d.description) LIKE LOWER(?)";
                    $params[] = '%' . $word . '%';
                    $params[] = '%' . $word . '%';
                }
            }
            
            // Add search without spaces (for titles like "Document Title" vs "DocumentTitle")
            $no_spaces = str_replace(' ', '', $clean_search);
            if ($no_spaces !== $clean_search) {
                $title_conditions[] = "LOWER(REPLACE(d.title, ' ', '')) LIKE LOWER(?)";
                $desc_conditions[] = "LOWER(REPLACE(d.description, ' ', '')) LIKE LOWER(?)";
                $params[] = '%' . $no_spaces . '%';
                $params[] = '%' . $no_spaces . '%';
            }
            
            $title_condition = '(' . implode(' OR ', $title_conditions) . ')';
            $desc_condition = '(' . implode(' OR ', $desc_conditions) . ')';
            
            $sql = "SELECT d.id, d.title, d.description, d.filename, d.visibility, d.uploaded_by, d.created_at, u.name as uploader_name,
                           -- Calculate relevance score for better sorting
                           (CASE 
                               WHEN LOWER(d.title) = LOWER(?) THEN 100
                               WHEN LOWER(d.title) LIKE LOWER(?) THEN 90
                               WHEN LOWER(REPLACE(d.title, ' ', '')) LIKE LOWER(?) THEN 80
                               ELSE 50
                           END) as relevance_score
                    FROM documents d 
                    LEFT JOIN users u ON d.uploaded_by = u.id 
                    WHERE ($title_condition OR $desc_condition)
                    ORDER BY relevance_score DESC, d.created_at DESC";
            
            // Add relevance parameters at the beginning
            $relevance_params = [$clean_search, '%' . $clean_search . '%', '%' . $no_spaces . '%'];
            $all_params = array_merge($relevance_params, $params);
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($all_params);
            $documents = $stmt->fetchAll();
        } else {
            $documents = [];
        }
    } else {
        $documents_stmt = $pdo->query("SELECT d.id, d.title, d.description, d.filename, d.visibility, d.uploaded_by, d.created_at, u.name as uploader_name 
                                       FROM documents d 
                                       LEFT JOIN users u ON d.uploaded_by = u.id 
                                       ORDER BY d.created_at DESC");
        $documents = $documents_stmt->fetchAll();
    }
} catch (PDOException $e) {
    $documents = [];
    error_log("Search error: " . $e->getMessage());
}

function highlightSearchTerm($text, $search) {
    if (empty($search)) return htmlspecialchars($text);
    
    $escaped_text = htmlspecialchars($text);
    
    // Highlight the exact search phrase first
    $highlighted = preg_replace('/(' . preg_quote($search, '/') . ')/i', '<mark>$1</mark>', $escaped_text);
    
    // Also highlight individual words from the search
    $search_words = explode(' ', trim($search));
    foreach ($search_words as $word) {
        $word = trim($word);
        if (strlen($word) >= 2) {
            $highlighted = preg_replace('/(' . preg_quote($word, '/') . ')/i', '<mark>$1</mark>', $highlighted);
        }
    }
    
    return $highlighted;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - LIMSys</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        /* Simple Municipal Color Scheme - Blue Main with Red/Yellow Accents */
        :root {
            --primary-blue: #2563EB;
            --primary-blue-dark: #1D4ED8;
            --primary-blue-light: #DBEAFE;
            --accent-red: #DC2626;
            --accent-red-light: #FEE2E2;
            --accent-yellow: #F59E0B;
            --accent-yellow-light: #FEF3C7;
            --neutral-gray: #6B7280;
            --neutral-light: #F9FAFB;
        }

        .sidebar {
            min-height: 100vh;
            background: var(--primary-blue);
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1rem;
            border-radius: 6px;
            margin-bottom: 0.25rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .sidebar .nav-link:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
        }

        .sidebar .nav-link.active {
            color: white;
            background: var(--accent-red);
        }
        
        .main-content {
            padding: 2rem;
            background: var(--neutral-light);
        }
        
        .stats-card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
        }
        
        .table-actions {
            white-space: nowrap;
        }
        
        .role-badge {
            font-size: 0.8rem;
        }
        
        .navbar-brand {
            font-weight: 600;
        }

        /* Sidebar logo styling */
        .sidebar-logo {
            height: 50px;
            width: auto;
            margin-bottom: 0.5rem;
        }
        
        mark {
            background-color: var(--accent-yellow-light);
            color: var(--primary-blue-dark);
            padding: 0.1em 0.2em;
            border-radius: 0.2em;
        }

        /* Card styling */
        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background: var(--primary-blue-light);
            border-bottom: 1px solid var(--primary-blue);
            border-radius: 8px 8px 0 0 !important;
        }

        .card-header h5 {
            color: var(--primary-blue-dark);
            font-weight: 600;
        }

        /* Table styling */
        .table-dark {
            background-color: var(--primary-blue-dark);
        }

        .table th {
            background-color: var(--primary-blue-light);
            color: var(--primary-blue-dark);
            font-weight: 600;
            border: none;
        }

        .table td {
            border-color: #E5E7EB;
        }

        /* Button styling */
        .btn-primary {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
        }

        .btn-primary:hover {
            background-color: var(--primary-blue-dark);
            border-color: var(--primary-blue-dark);
        }

        .btn-outline-primary {
            color: var(--primary-blue);
            border-color: var(--primary-blue);
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
        }

        .btn-danger {
            background-color: var(--accent-red);
            border-color: var(--accent-red);
        }

        /* Form controls */
        .form-control:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px var(--primary-blue-light);
        }

        /* Alert styling */
        .alert-info {
            background-color: var(--primary-blue-light);
            border-color: var(--primary-blue);
            color: var(--primary-blue-dark);
        }

        .alert-warning {
            background-color: var(--accent-yellow-light);
            border-color: var(--accent-yellow);
            color: #92400E;
        }

        /* Badge styling */
        .badge.bg-warning {
            background-color: var(--accent-yellow) !important;
            color: white !important;
        }

        .badge.bg-success {
            background-color: #10B981 !important;
        }

        .badge.bg-danger {
            background-color: var(--accent-red) !important;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar p-3">
                    <div class="text-center mb-4">
                        <div class="mb-2">
                            <img src="logo.png" alt="LIMSys" class="sidebar-logo">
                        </div>
                        <small class="text-white-50">Admin Panel</small>
                    </div>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link" href="#users-section">
                            <i class="bi bi-people me-2"></i>User Management
                        </a>
                        <a class="nav-link" href="#documents-section">
                            <i class="bi bi-file-earmark-text me-2"></i>Document Management
                        </a>
                        <a class="nav-link active" href="#search-section">
                            <i class="bi bi-search me-2"></i>Search Documents
                        </a>
                        <a class="nav-link" href="admin_reset_password.php">
                            <i class="bi bi-key me-2"></i>Password Resets
                            <?php if ($pending_resets_count > 0): ?>
                                <span class="badge bg-warning text-dark ms-auto"><?php echo $pending_resets_count; ?></span>
                            <?php endif; ?>
                        </a>
                        <a class="nav-link" href="#statistics">
                            <i class="bi bi-graph-up me-2"></i>Statistics
                        </a>
                        <a class="nav-link" href="profile.php">
                            <i class="bi bi-person-circle me-2"></i>My Profile
                        </a>
                        <hr class="text-white-50">
                        <a class="nav-link" href="index.php">
                            <i class="bi bi-house me-2"></i>Home
                        </a>
                        <a class="nav-link" href="logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i>Logout
                        </a>
                    </nav>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h3">Admin Dashboard</h1>
                        <div class="text-muted">
                            Welcome, <?php echo htmlspecialchars($_SESSION['email']); ?>
                        </div>
                    </div>
                    
                    <!-- Alert Messages -->
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Statistics Cards -->
                    <div id="statistics" class="row mb-4">
                        <div class="col-md-3">
                            <div class="card stats-card text-center p-3">
                                <div class="card-body">
                                    <i class="bi bi-people fs-1 text-primary"></i>
                                    <h3 class="mt-2"><?php echo count($users); ?></h3>
                                    <p class="text-muted mb-0">Total Users</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card text-center p-3">
                                <div class="card-body">
                                    <i class="bi bi-file-earmark-text fs-1 text-success"></i>
                                    <h3 class="mt-2"><?php echo count($documents); ?></h3>
                                    <p class="text-muted mb-0">Total Documents</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card text-center p-3">
                                <div class="card-body">
                                    <i class="bi bi-shield-check fs-1 text-warning"></i>
                                    <h3 class="mt-2"><?php echo count(array_filter($users, fn($u) => $u['role'] === 'admin')); ?></h3>
                                    <p class="text-muted mb-0">Administrators</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card text-center p-3">
                                <div class="card-body">
                                    <i class="bi bi-eye fs-1 text-info"></i>
                                    <h3 class="mt-2"><?php echo count(array_filter($documents, fn($d) => $d['visibility'] === 'Public')); ?></h3>
                                    <p class="text-muted mb-0">Public Documents</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- User Management Section -->
                    <div id="users-section" class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-people me-2"></i>User Management
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <span class="badge role-badge 
                                                    <?php echo $user['role'] === 'admin' ? 'bg-danger' : 
                                                               ($user['role'] === 'user' ? 'bg-primary' : 'bg-secondary'); ?>">
                                                    <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'warning'; ?>">
                                                    <?php echo ucfirst(htmlspecialchars($user['status'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                            <td class="table-actions">
                                                <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                                    <!-- Role Change Form -->
                                                    <form method="POST" class="d-inline me-1">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <select name="new_role" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                                                            <option value="">Change Role</option>
                                                            <option value="admin" <?php echo $user['role'] === 'admin' ? 'disabled' : ''; ?>>Admin</option>
                                                            <option value="user" <?php echo $user['role'] === 'user' ? 'disabled' : ''; ?>>User</option>
                                                            <option value="guest" <?php echo $user['role'] === 'guest' ? 'disabled' : ''; ?>>Guest</option>
                                                        </select>
                                                        <input type="hidden" name="change_role" value="1">
                                                    </form>
                                                    
                                                    <!-- Delete User Form -->
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?')">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" name="delete_user" class="btn btn-danger btn-sm">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted small">Current User</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                
                                <?php if (empty($users)): ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="bi bi-people fs-1"></i>
                                    <p class="mt-2">No users found.</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Search Section -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-search me-2"></i>Search Documents
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" action="">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input type="text" 
                                           name="search" 
                                           class="form-control" 
                                           placeholder="Search all documents by title or description..."
                                           value="<?php echo htmlspecialchars($search_query); ?>"
                                           autocomplete="off">
                                    <button type="submit" class="btn btn-primary">Search</button>
                                </div>
                                <?php if (!empty($search_query)): ?>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        Showing results for "<?php echo htmlspecialchars($search_query); ?>" (<?php echo count($documents); ?> documents found)
                                        <a href="admin_dashboard.php" class="ms-2 text-decoration-none">
                                            <i class="bi bi-x-circle me-1"></i>Clear Search
                                        </a>
                                    </small>
                                </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <!-- Document Management Section -->
                    <div id="documents-section" class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-file-earmark-text me-2"></i>Document Management
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Title</th>
                                            <th>Filename</th>
                                            <th>Visibility</th>
                                            <th>Uploaded By</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($documents as $document): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($document['id']); ?></td>
                                            <td>
                                                <?php echo highlightSearchTerm($document['title'], $search_query); ?>
                                                <?php if (!empty($document['description']) && !empty($search_query)): ?>
                                                    <br><small class="text-muted">
                                                        <?php 
                                                        $desc = strlen($document['description']) > 100 ? substr($document['description'], 0, 100) . '...' : $document['description'];
                                                        echo highlightSearchTerm($desc, $search_query); 
                                                        ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($document['filename']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $document['visibility'] === 'Public' ? 'success' : 'warning'; ?>">
                                                    <?php echo htmlspecialchars($document['visibility']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($document['uploader_name'] ?? 'Unknown'); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($document['created_at'])); ?></td>
                                            <td class="table-actions">
                                                <div class="btn-group" role="group">
                                                    <a href="view.php?id=<?php echo $document['id']; ?>" 
                                                       class="btn btn-outline-info btn-sm" 
                                                       title="View Document">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="download.php?id=<?php echo $document['id']; ?>" 
                                                       class="btn btn-outline-success btn-sm" 
                                                       title="Download Document">
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this document?')">
                                                        <input type="hidden" name="document_id" value="<?php echo $document['id']; ?>">
                                                        <button type="submit" name="delete_document" class="btn btn-danger btn-sm" title="Delete Document">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                
                                <?php if (empty($documents)): ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="bi bi-file-earmark-text fs-1"></i>
                                    <p class="mt-2">No documents found.</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });
    </script>
</body>
</html>
