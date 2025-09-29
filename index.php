<?php
/**
 * LIMSys Public Dashboard
 * Guest access to public documents with search and download functionality
 */

session_start();
require_once 'db.php';

$search_query = '';
$message = '';
$message_type = '';

// Handle search functionality
if (isset($_GET['search'])) {
    $search_query = trim($_GET['search']);
}

// Fetch public documents with enhanced search filtering
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
            
            $sql = "SELECT d.id, d.title, d.description, d.filename, d.original_filename, d.file_size, d.uploaded_by, d.created_at, u.name as uploader_name,
                           -- Calculate relevance score for better sorting
                           (CASE 
                               WHEN LOWER(d.title) = LOWER(?) THEN 100
                               WHEN LOWER(d.title) LIKE LOWER(?) THEN 90
                               WHEN LOWER(REPLACE(d.title, ' ', '')) LIKE LOWER(?) THEN 80
                               ELSE 50
                           END) as relevance_score
                    FROM documents d 
                    LEFT JOIN users u ON d.uploaded_by = u.id 
                    WHERE d.visibility = 'Public' AND ($title_condition OR $desc_condition)
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
        // Show all public documents
        $documents_stmt = $pdo->query("SELECT d.id, d.title, d.description, d.filename, d.original_filename, d.file_size, d.uploaded_by, d.created_at, u.name as uploader_name 
                                       FROM documents d 
                                       LEFT JOIN users u ON d.uploaded_by = u.id 
                                       WHERE d.visibility = 'Public'
                                       ORDER BY d.created_at DESC");
        $documents = $documents_stmt->fetchAll();
    }
} catch (PDOException $e) {
    $documents = [];
    error_log("Public documents search error: " . $e->getMessage());
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

function formatFileSize($size) {
    if ($size < 1024) return $size . ' B';
    if ($size < 1048576) return round($size / 1024, 2) . ' KB';
    if ($size < 1073741824) return round($size / 1048576, 2) . ' MB';
    return round($size / 1073741824, 2) . ' GB';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LIMSys - Public Documents</title>
    
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

        .navbar-brand img {
            height: 40px;
            width: auto;
        }
        
        .hero-section {
            background: var(--primary-blue);
            color: white;
            padding: 60px 0;
        }
        
        .search-section {
            background: var(--neutral-light);
            padding: 40px 0;
        }
        
        .document-card {
            border: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease;
            height: 100%;
            border-radius: 8px;
        }
        
        .document-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
        }
        
        .btn-custom {
            background: var(--primary-blue);
            border: none;
            color: white;
            font-weight: 500;
            transition: background-color 0.2s ease;
            border-radius: 6px;
        }
        
        .btn-custom:hover {
            background: var(--primary-blue-dark);
            color: white;
        }
        
        .btn-outline-custom {
            border: 1px solid var(--primary-blue);
            color: var(--primary-blue);
            font-weight: 500;
            transition: all 0.2s ease;
            border-radius: 6px;
        }
        
        .btn-outline-custom:hover {
            background: var(--primary-blue);
            color: white;
        }
        
        .stats-card {
            background: var(--primary-blue);
            color: white;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .document-meta {
            font-size: 0.9rem;
            color: var(--neutral-gray);
        }
        
        .document-title {
            color: var(--primary-blue-dark);
            text-decoration: none;
            font-weight: 600;
        }
        
        .document-title:hover {
            color: var(--accent-red);
        }
        
        mark {
            background-color: var(--accent-yellow-light);
            color: var(--primary-blue-dark);
            padding: 2px 4px;
            border-radius: 3px;
        }
        
        .footer {
            background: var(--primary-blue);
            color: white;
            padding: 40px 0;
        }

        /* Enhanced navbar styling */
        .navbar {
            border-bottom: 1px solid #E5E7EB;
        }

        .nav-link {
            color: var(--neutral-gray) !important;
            font-weight: 500;
        }

        .nav-link.active {
            color: var(--primary-blue) !important;
        }

        .nav-link:hover {
            color: var(--primary-blue-dark) !important;
        }

        /* Table styling for documents */
        .table th {
            background-color: var(--primary-blue-light);
            color: var(--primary-blue-dark);
            font-weight: 600;
            border: none;
        }

        .table td {
            border-color: #E5E7EB;
        }

        /* Search form styling */
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
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <!-- Custom logo -->
                <img src="logo.png" alt="LIMSys" class="me-2">
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">
                            <i class="bi bi-house me-1"></i>Public Documents
                        </a>
                    </li>
                </ul>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="d-flex align-items-center">
                        <span class="me-3">Welcome, <?php echo htmlspecialchars($_SESSION['email']); ?></span>
                        <a href="<?php echo $_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : 'user_dashboard.php'; ?>" class="btn btn-outline-custom me-2">Dashboard</a>
                        <a href="logout.php" class="btn btn-outline-secondary">Logout</a>
                    </div>
                <?php else: ?>
                    <div class="d-flex">
                        <a href="login.php" class="btn btn-outline-custom me-2">Login</a>
                        <a href="register.php" class="btn btn-custom">Register</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-4 fw-bold">LIMSys Public Repository</h1>
                    <p class="lead">
                        Explore our collection of public documents and research materials. 
                        Search, view, and download documents shared by our community.
                    </p>
                </div>
                <div class="col-lg-4">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo count($documents); ?></div>
                        <p class="mb-0">Public Documents Available</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Search Section -->
    <section class="search-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card border-0 shadow">
                        <div class="card-body p-4">
                            <h4 class="card-title text-center mb-4">
                                <i class="bi bi-search me-2"></i>Search Public Documents
                            </h4>
                            <form method="GET" action="">
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input type="text" 
                                           name="search" 
                                           class="form-control" 
                                           placeholder="Search documents by title or description..."
                                           value="<?php echo htmlspecialchars($search_query); ?>"
                                           autocomplete="off">
                                    <button type="submit" class="btn btn-custom px-4">Search</button>
                                </div>
                                <?php if (!empty($search_query)): ?>
                                <div class="mt-3 text-center">
                                    <small class="text-muted">
                                        Showing results for "<?php echo htmlspecialchars($search_query); ?>" (<?php echo count($documents); ?> documents found)
                                        <a href="index.php" class="ms-2 text-decoration-none">
                                            <i class="bi bi-x-circle me-1"></i>Clear Search
                                        </a>
                                    </small>
                                </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Documents Section -->
    <section class="py-5">
        <div class="container">
            <?php if (!empty($documents)): ?>
                <div class="row g-4">
                    <?php foreach ($documents as $document): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="card document-card">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <a href="view.php?id=<?php echo $document['id']; ?>" class="document-title">
                                        <?php echo highlightSearchTerm($document['title'], $search_query); ?>
                                    </a>
                                </h5>
                                
                                <?php if (!empty($document['description'])): ?>
                                    <p class="card-text">
                                        <?php 
                                        $desc = strlen($document['description']) > 150 ? substr($document['description'], 0, 150) . '...' : $document['description'];
                                        echo highlightSearchTerm($desc, $search_query); 
                                        ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="document-meta mb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span>
                                            <i class="bi bi-file-earmark me-1"></i>
                                            <?php echo formatFileSize($document['file_size'] ?? 0); ?>
                                        </span>
                                        <span>
                                            <i class="bi bi-calendar me-1"></i>
                                            <?php echo date('M j, Y', strtotime($document['created_at'])); ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($document['uploader_name'])): ?>
                                        <div class="mt-1">
                                            <i class="bi bi-person me-1"></i>
                                            by <?php echo htmlspecialchars($document['uploader_name']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <a href="view.php?id=<?php echo $document['id']; ?>" 
                                       class="btn btn-outline-primary btn-sm flex-fill">
                                        <i class="bi bi-eye me-1"></i>View
                                    </a>
                                    <a href="download.php?id=<?php echo $document['id']; ?>" 
                                       class="btn btn-custom btn-sm flex-fill">
                                        <i class="bi bi-download me-1"></i>Download
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-search fs-1 text-muted"></i>
                    <h3 class="mt-3 text-muted">
                        <?php echo !empty($search_query) ? 'No documents found' : 'No public documents available'; ?>
                    </h3>
                    <p class="text-muted">
                        <?php if (!empty($search_query)): ?>
                            Try adjusting your search terms or <a href="index.php">browse all documents</a>.
                        <?php else: ?>
                            Public documents will appear here when they are uploaded by users.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Call to Action -->
    <?php if (!isset($_SESSION['user_id'])): ?>
    <section class="py-5 bg-light">
        <div class="container text-center">
            <h2 class="display-6 fw-bold mb-4">Want to Upload Your Own Documents?</h2>
            <p class="lead mb-4">
                Join our community to upload, manage, and share your research documents.
            </p>
            <div class="d-flex justify-content-center gap-3">
                <a href="register.php" class="btn btn-custom btn-lg">
                    <i class="bi bi-person-plus me-2"></i>Create Account
                </a>
                <a href="login.php" class="btn btn-outline-custom btn-lg">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5 class="text-white mb-3">
                        LIMSys
                    </h5>
                    <p class="text-light">
                        Legislative Information Management System for Enhancing Organizational Efficiency Through a Smart Document Management Platform.
                    </p>
                </div>
                <div class="col-md-3">
                    <h6 class="text-white mb-3">Quick Links</h6>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-light text-decoration-none">Public Documents</a></li>
                        <li><a href="login.php" class="text-light text-decoration-none">Login</a></li>
                        <li><a href="register.php" class="text-light text-decoration-none">Register</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h6 class="text-white mb-3">Contact</h6>
                    <p class="text-light mb-1">
                        <i class="bi bi-envelope me-2"></i>info@limsys.com
                    </p>
                    <p class="text-light">
                        <i class="bi bi-telephone me-2"></i>+1 (555) 123-4567
                    </p>
                </div>
            </div>
            <hr class="my-4 bg-light">
            <div class="text-center">
                <p class="text-light mb-0">&copy; 2024 LIMSys. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>