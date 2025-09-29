<?php
/**
 * LIMSys User Dashboard
 * User interface for managing personal documents
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'db.php';

$message = '';
$message_type = '';
$user_id = $_SESSION['user_id'];
$search_query = '';

// Handle search functionality
if (isset($_GET['search'])) {
    $search_query = trim($_GET['search']);
}

// Handle document deletion
if (isset($_POST['delete_document'])) {
    $document_id = (int)$_POST['document_id'];
    
    try {
        // Verify the document belongs to the current user
        $stmt = $pdo->prepare("SELECT id FROM documents WHERE id = ? AND uploaded_by = ?");
        $stmt->execute([$document_id, $user_id]);
        
        if ($stmt->fetch()) {
            $delete_stmt = $pdo->prepare("DELETE FROM documents WHERE id = ? AND uploaded_by = ?");
            if ($delete_stmt->execute([$document_id, $user_id])) {
                $message = "Document deleted successfully.";
                $message_type = "success";
            }
        } else {
            $message = "Document not found or you don't have permission to delete it.";
            $message_type = "warning";
        }
    } catch (PDOException $e) {
        $message = "Error deleting document.";
        $message_type = "danger";
    }
}

// Handle document visibility update
if (isset($_POST['update_visibility'])) {
    $document_id = (int)$_POST['document_id'];
    $new_visibility = $_POST['visibility'];
    
    if (in_array($new_visibility, ['Public', 'Private'])) {
        try {
            $stmt = $pdo->prepare("UPDATE documents SET visibility = ? WHERE id = ? AND uploaded_by = ?");
            if ($stmt->execute([$new_visibility, $document_id, $user_id])) {
                $message = "Document visibility updated successfully.";
                $message_type = "success";
            }
        } catch (PDOException $e) {
            $message = "Error updating document visibility.";
            $message_type = "danger";
        }
    }
}

// Fetch user's documents with enhanced search filtering
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
            $params = [$user_id]; // Start with user_id parameter
            
            // Add exact phrase match (highest priority)
            $title_conditions[] = "LOWER(title) LIKE LOWER(?)";
            $desc_conditions[] = "LOWER(description) LIKE LOWER(?)";
            $params[] = '%' . $clean_search . '%';
            $params[] = '%' . $clean_search . '%';
            
            // Add individual word matches for flexibility
            foreach ($search_words as $word) {
                if (strlen($word) >= 2) { // Only include words with 2+ characters
                    $title_conditions[] = "LOWER(title) LIKE LOWER(?)";
                    $desc_conditions[] = "LOWER(description) LIKE LOWER(?)";
                    $params[] = '%' . $word . '%';
                    $params[] = '%' . $word . '%';
                }
            }
            
            // Add search without spaces (for titles like "Document Title" vs "DocumentTitle")
            $no_spaces = str_replace(' ', '', $clean_search);
            if ($no_spaces !== $clean_search) {
                $title_conditions[] = "LOWER(REPLACE(title, ' ', '')) LIKE LOWER(?)";
                $desc_conditions[] = "LOWER(REPLACE(description, ' ', '')) LIKE LOWER(?)";
                $params[] = '%' . $no_spaces . '%';
                $params[] = '%' . $no_spaces . '%';
            }
            
            $title_condition = '(' . implode(' OR ', $title_conditions) . ')';
            $desc_condition = '(' . implode(' OR ', $desc_conditions) . ')';
            
            $sql = "SELECT id, title, description, filename, visibility, file_size, created_at,
                           -- Calculate relevance score for better sorting
                           (CASE 
                               WHEN LOWER(title) = LOWER(?) THEN 100
                               WHEN LOWER(title) LIKE LOWER(?) THEN 90
                               WHEN LOWER(REPLACE(title, ' ', '')) LIKE LOWER(?) THEN 80
                               ELSE 50
                           END) as relevance_score
                    FROM documents 
                    WHERE uploaded_by = ? AND ($title_condition OR $desc_condition)
                    ORDER BY relevance_score DESC, created_at DESC";
            
            // Add relevance parameters
            $relevance_params = [$clean_search, '%' . $clean_search . '%', '%' . $no_spaces . '%', $user_id];
            $all_params = array_merge($relevance_params, array_slice($params, 1)); // Remove first user_id since it's in relevance_params
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($all_params);
            $documents = $stmt->fetchAll();
        } else {
            $documents = [];
        }
    } else {
        $stmt = $pdo->prepare("SELECT id, title, description, filename, visibility, file_size, created_at FROM documents WHERE uploaded_by = ? ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        $documents = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $documents = [];
    error_log("User dashboard search error: " . $e->getMessage());
}

// Get user statistics
try {
    $total_docs = count($documents);
    $public_docs = count(array_filter($documents, fn($d) => $d['visibility'] === 'Public'));
    $private_docs = $total_docs - $public_docs;
    $total_size = array_sum(array_column($documents, 'file_size'));
} catch (Exception $e) {
    $total_docs = $public_docs = $private_docs = $total_size = 0;
}

function formatFileSize($size) {
    if ($size < 1024) return $size . ' B';
    if ($size < 1048576) return round($size / 1024, 2) . ' KB';
    if ($size < 1073741824) return round($size / 1048576, 2) . ' MB';
    return round($size / 1073741824, 2) . ' GB';
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
    <title>User Dashboard - LIMSys</title>
    
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
        
        .upload-area {
            border: 2px dashed #D1D5DB;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            background: var(--neutral-light);
            transition: all 0.2s ease;
        }
        
        .upload-area:hover {
            border-color: var(--primary-blue);
            background: var(--primary-blue-light);
        }
        
        .btn-primary-custom {
            background: var(--primary-blue);
            border: none;
            color: white;
            font-weight: 500;
            border-radius: 6px;
            transition: background-color 0.2s ease;
        }
        
        .btn-primary-custom:hover {
            background: var(--primary-blue-dark);
            color: white;
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

        /* Button styling */
        .btn-primary {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
        }

        .btn-primary:hover {
            background-color: var(--primary-blue-dark);
            border-color: var(--primary-blue-dark);
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

        /* Table styling */
        .table th {
            background-color: var(--primary-blue-light);
            color: var(--primary-blue-dark);
            font-weight: 600;
            border: none;
        }

        .table td {
            border-color: #E5E7EB;
        }

        /* Sidebar logo styling */
        .sidebar-logo {
            height: 50px;
            width: auto;
            margin-bottom: 0.5rem;
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
                        <small class="text-white-50">User Panel</small>
                    </div>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link" href="#search-section">
                            <i class="bi bi-search me-2"></i>Search Documents
                        </a>
                        <a class="nav-link active" href="#documents-section">
                            <i class="bi bi-file-earmark-text me-2"></i>My Documents
                        </a>
                        <a class="nav-link" href="#upload-section">
                            <i class="bi bi-cloud-upload me-2"></i>Upload Document
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
                        <div>
                            <h1 class="h3">My Dashboard</h1>
                            <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($_SESSION['email']); ?></p>
                        </div>
                        <a href="upload.php" class="btn btn-primary-custom">
                            <i class="bi bi-cloud-upload me-2"></i>Upload Document
                        </a>
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
                                    <i class="bi bi-file-earmark-text fs-1 text-primary"></i>
                                    <h3 class="mt-2"><?php echo $total_docs; ?></h3>
                                    <p class="text-muted mb-0">Total Documents</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card text-center p-3">
                                <div class="card-body">
                                    <i class="bi bi-eye fs-1 text-success"></i>
                                    <h3 class="mt-2"><?php echo $public_docs; ?></h3>
                                    <p class="text-muted mb-0">Public Documents</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card text-center p-3">
                                <div class="card-body">
                                    <i class="bi bi-eye-slash fs-1 text-warning"></i>
                                    <h3 class="mt-2"><?php echo $private_docs; ?></h3>
                                    <p class="text-muted mb-0">Private Documents</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card text-center p-3">
                                <div class="card-body">
                                    <i class="bi bi-hdd fs-1 text-info"></i>
                                    <h3 class="mt-2"><?php echo formatFileSize($total_size); ?></h3>
                                    <p class="text-muted mb-0">Total Size</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Search Section -->
                    <div id="search-section" class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-search me-2"></i>Search My Documents
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
                                           placeholder="Search by title or description..."
                                           value="<?php echo htmlspecialchars($search_query); ?>"
                                           autocomplete="off">
                                    <button type="submit" class="btn btn-primary-custom">Search</button>
                                </div>
                                <?php if (!empty($search_query)): ?>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        Showing results for "<?php echo htmlspecialchars($search_query); ?>"
                                        <a href="user_dashboard.php" class="ms-2 text-decoration-none">
                                            <i class="bi bi-x-circle me-1"></i>Clear Search
                                        </a>
                                    </small>
                                </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <!-- Upload Section -->
                    <div id="upload-section" class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-cloud-upload me-2"></i>Quick Upload
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="upload-area">
                                <i class="bi bi-cloud-upload fs-1 text-muted"></i>
                                <h5 class="mt-3">Upload New Document</h5>
                                <p class="text-muted">Click the button below to upload a new document to your collection</p>
                                <a href="upload.php" class="btn btn-primary-custom btn-lg">
                                    <i class="bi bi-plus-circle me-2"></i>Choose File to Upload
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Documents Management Section -->
                    <div id="documents-section" class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-file-earmark-text me-2"></i>My Documents
                            </h5>
                            <span class="badge bg-primary"><?php echo $total_docs; ?> Documents</span>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($documents)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Title</th>
                                                <th>Filename</th>
                                                <th>Size</th>
                                                <th>Visibility</th>
                                                <th>Uploaded</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($documents as $document): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo highlightSearchTerm($document['title'], $search_query); ?></strong>
                                                    <?php if (!empty($document['description']) && !empty($search_query)): ?>
                                                        <br><small class="text-muted">
                                                            <?php 
                                                            $desc = strlen($document['description']) > 100 ? substr($document['description'], 0, 100) . '...' : $document['description'];
                                                            echo highlightSearchTerm($desc, $search_query); 
                                                            ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <i class="bi bi-file-earmark me-1"></i>
                                                    <?php echo htmlspecialchars($document['filename']); ?>
                                                </td>
                                                <td><?php echo formatFileSize($document['file_size'] ?? 0); ?></td>
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="document_id" value="<?php echo $document['id']; ?>">
                                                        <select name="visibility" class="form-select form-select-sm" onchange="this.form.submit()" style="width: auto;">
                                                            <option value="Public" <?php echo $document['visibility'] === 'Public' ? 'selected' : ''; ?>>
                                                                🌐 Public
                                                            </option>
                                                            <option value="Private" <?php echo $document['visibility'] === 'Private' ? 'selected' : ''; ?>>
                                                                🔒 Private
                                                            </option>
                                                        </select>
                                                        <input type="hidden" name="update_visibility" value="1">
                                                    </form>
                                                </td>
                                                <td><?php echo date('M j, Y g:i A', strtotime($document['created_at'])); ?></td>
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
                                                        <a href="edit_document.php?id=<?php echo $document['id']; ?>" 
                                                           class="btn btn-outline-primary btn-sm" 
                                                           title="Edit Document">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <form method="POST" class="d-inline" 
                                                              onsubmit="return confirm('Are you sure you want to delete this document? This action cannot be undone.')">
                                                            <input type="hidden" name="document_id" value="<?php echo $document['id']; ?>">
                                                            <button type="submit" name="delete_document" 
                                                                    class="btn btn-outline-danger btn-sm" 
                                                                    title="Delete Document">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-file-earmark-text" style="font-size: 4rem; color: #dee2e6;"></i>
                                    <h4 class="mt-3 text-muted">No Documents Yet</h4>
                                    <p class="text-muted">You haven't uploaded any documents yet. Get started by uploading your first document!</p>
                                    <a href="upload.php" class="btn btn-primary-custom btn-lg">
                                        <i class="bi bi-cloud-upload me-2"></i>Upload Your First Document
                                    </a>
                                </div>
                            <?php endif; ?>
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
        
        // Add confirmation for delete actions
        document.querySelectorAll('form[onsubmit*="confirm"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!confirm('Are you sure you want to delete this document? This action cannot be undone.')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
