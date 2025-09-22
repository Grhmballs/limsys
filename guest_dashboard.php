<?php
/**
 * LIMSys Guest Dashboard
 * Public document access and search for guest users
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'db.php';

$search_query = '';
$documents = [];

// Handle search functionality
if (isset($_GET['search'])) {
    $search_query = trim($_GET['search']);
}

// Fetch public documents with search filtering
try {
    if (!empty($search_query)) {
        $stmt = $pdo->prepare("SELECT d.id, d.title, d.description, d.filename, d.file_size, d.created_at, u.name as uploader_name 
                               FROM documents d 
                               LEFT JOIN users u ON d.uploaded_by = u.id 
                               WHERE d.visibility = 'Public' 
                               AND (d.title LIKE ? OR d.description LIKE ? OR d.filename LIKE ?)
                               ORDER BY d.created_at DESC");
        $search_param = '%' . $search_query . '%';
        $stmt->execute([$search_param, $search_param, $search_param]);
    } else {
        $stmt = $pdo->prepare("SELECT d.id, d.title, d.description, d.filename, d.file_size, d.created_at, u.name as uploader_name 
                               FROM documents d 
                               LEFT JOIN users u ON d.uploaded_by = u.id 
                               WHERE d.visibility = 'Public' 
                               ORDER BY d.created_at DESC");
        $stmt->execute();
    }
    $documents = $stmt->fetchAll();
} catch (PDOException $e) {
    $documents = [];
}

// Get statistics
$total_public_docs = count($documents);

function formatFileSize($size) {
    if ($size < 1024) return $size . ' B';
    if ($size < 1048576) return round($size / 1024, 2) . ' KB';
    if ($size < 1073741824) return round($size / 1048576, 2) . ' MB';
    return round($size / 1073741824, 2) . ' GB';
}

function highlightSearchTerm($text, $search) {
    if (empty($search)) return htmlspecialchars($text);
    $highlighted = preg_replace('/(' . preg_quote($search, '/') . ')/i', '<mark>$1</mark>', htmlspecialchars($text));
    return $highlighted;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guest Dashboard - LIMSys</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1rem;
            border-radius: 0.375rem;
            margin-bottom: 0.25rem;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .main-content {
            padding: 2rem;
        }
        
        .stats-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
        }
        
        .search-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box .form-control {
            border-radius: 25px;
            padding: 0.75rem 1rem 0.75rem 3rem;
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .search-box .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .document-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .document-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        .download-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            border-radius: 20px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: transform 0.2s ease;
        }
        
        .download-btn:hover {
            color: white;
            transform: translateY(-1px);
        }
        
        .no-results {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
        
        mark {
            background-color: #fff3cd;
            padding: 0.1em 0.2em;
            border-radius: 0.2em;
        }
        
        .document-meta {
            font-size: 0.875rem;
            color: #6c757d;
        }
        
        .search-results-header {
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
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
                        <h4 class="text-white">
                            <i class="bi bi-clipboard-data me-2"></i>LIMSys
                        </h4>
                        <small class="text-white-50">Guest Access</small>
                    </div>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link active" href="#search-section">
                            <i class="bi bi-search me-2"></i>Search Documents
                        </a>
                        <a class="nav-link" href="#documents-section">
                            <i class="bi bi-file-earmark-text me-2"></i>Public Documents
                        </a>
                        <a class="nav-link" href="#statistics">
                            <i class="bi bi-graph-up me-2"></i>Statistics
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
                            <h1 class="h3">Guest Dashboard</h1>
                            <p class="text-muted mb-0">Browse and search public documents</p>
                        </div>
                        <div class="text-muted">
                            Welcome, <?php echo htmlspecialchars($_SESSION['email']); ?>
                        </div>
                    </div>
                    
                    <!-- Search Section -->
                    <div id="search-section" class="search-section">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h3 class="mb-3">
                                    <i class="bi bi-search me-2"></i>Search Public Documents
                                </h3>
                                <form method="GET" action="">
                                    <div class="search-box">
                                        <i class="bi bi-search search-icon"></i>
                                        <input type="text" 
                                               name="search" 
                                               class="form-control form-control-lg" 
                                               placeholder="Search by title, description, or filename..."
                                               value="<?php echo htmlspecialchars($search_query); ?>"
                                               autocomplete="off">
                                    </div>
                                    <small class="text-white-50 mt-2 d-block">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Search through titles, descriptions, and filenames of public documents
                                    </small>
                                </form>
                            </div>
                            <div class="col-md-4 text-center">
                                <i class="bi bi-files" style="font-size: 4rem; opacity: 0.3;"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Statistics Card -->
                    <div id="statistics" class="row mb-4">
                        <div class="col-md-12">
                            <div class="card stats-card">
                                <div class="card-body">
                                    <div class="row text-center">
                                        <div class="col-md-4">
                                            <i class="bi bi-file-earmark-text fs-1 text-primary"></i>
                                            <h3 class="mt-2"><?php echo $total_public_docs; ?></h3>
                                            <p class="text-muted mb-0">
                                                <?php echo !empty($search_query) ? 'Search Results' : 'Public Documents'; ?>
                                            </p>
                                        </div>
                                        <div class="col-md-4">
                                            <i class="bi bi-eye fs-1 text-success"></i>
                                            <h3 class="mt-2">Public</h3>
                                            <p class="text-muted mb-0">Access Level</p>
                                        </div>
                                        <div class="col-md-4">
                                            <i class="bi bi-download fs-1 text-info"></i>
                                            <h3 class="mt-2">Free</h3>
                                            <p class="text-muted mb-0">Download Access</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Search Results Header -->
                    <?php if (!empty($search_query)): ?>
                    <div class="search-results-header">
                        <h4>
                            <i class="bi bi-search me-2"></i>
                            Search Results for "<?php echo htmlspecialchars($search_query); ?>"
                        </h4>
                        <p class="text-muted mb-0">
                            Found <?php echo $total_public_docs; ?> document(s)
                            <a href="guest_dashboard.php" class="ms-3 text-decoration-none">
                                <i class="bi bi-x-circle me-1"></i>Clear Search
                            </a>
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Documents Section -->
                    <div id="documents-section">
                        <?php if (!empty($documents)): ?>
                            <div class="row g-4">
                                <?php foreach ($documents as $document): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card document-card">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <h5 class="card-title mb-0">
                                                    <?php echo highlightSearchTerm($document['title'], $search_query); ?>
                                                </h5>
                                                <span class="badge bg-success">Public</span>
                                            </div>
                                            
                                            <p class="card-text text-muted mb-3">
                                                <?php 
                                                $description = $document['description'] ?? 'No description available.';
                                                $truncated = strlen($description) > 100 ? substr($description, 0, 100) . '...' : $description;
                                                echo highlightSearchTerm($truncated, $search_query);
                                                ?>
                                            </p>
                                            
                                            <div class="document-meta mb-3">
                                                <div class="d-flex align-items-center mb-1">
                                                    <i class="bi bi-file-earmark me-2"></i>
                                                    <span><?php echo highlightSearchTerm($document['filename'], $search_query); ?></span>
                                                </div>
                                                <div class="d-flex align-items-center mb-1">
                                                    <i class="bi bi-hdd me-2"></i>
                                                    <span><?php echo formatFileSize($document['file_size'] ?? 0); ?></span>
                                                </div>
                                                <div class="d-flex align-items-center mb-1">
                                                    <i class="bi bi-person me-2"></i>
                                                    <span><?php echo htmlspecialchars($document['uploader_name'] ?? 'Unknown'); ?></span>
                                                </div>
                                                <div class="d-flex align-items-center">
                                                    <i class="bi bi-calendar me-2"></i>
                                                    <span><?php echo date('M j, Y', strtotime($document['created_at'])); ?></span>
                                                </div>
                                            </div>
                                            
                                            <div class="d-grid">
                                                <a href="download.php?id=<?php echo $document['id']; ?>" 
                                                   class="btn download-btn">
                                                    <i class="bi bi-download me-2"></i>Download
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-results">
                                <?php if (!empty($search_query)): ?>
                                    <i class="bi bi-search" style="font-size: 4rem;"></i>
                                    <h4 class="mt-3">No Documents Found</h4>
                                    <p>No public documents match your search criteria "<?php echo htmlspecialchars($search_query); ?>"</p>
                                    <a href="guest_dashboard.php" class="btn btn-outline-primary">
                                        <i class="bi bi-arrow-left me-2"></i>Browse All Documents
                                    </a>
                                <?php else: ?>
                                    <i class="bi bi-file-earmark-text" style="font-size: 4rem;"></i>
                                    <h4 class="mt-3">No Public Documents</h4>
                                    <p>There are currently no public documents available to view.</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-submit search form on input with debounce
        let searchTimeout;
        const searchInput = document.querySelector('input[name="search"]');
        
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });
        
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
        
        // Focus search input on page load if no search query
        if (!searchInput.value) {
            searchInput.focus();
        }
    </script>
</body>
</html>
