<?php
/**
 * LIMSys Document View Page
 * Display document details and version history with access control
 */

session_start();

// Users don't need to be logged in to view public documents
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['role'] ?? 'guest';

require_once 'db.php';

$document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$document_id) {
    header('Location: ' . ($user_role === 'admin' ? 'admin_dashboard.php' : 
                           ($user_role === 'user' ? 'user_dashboard.php' : 'index.php')));
    exit();
}

// Fetch document details
try {
    $stmt = $pdo->prepare("SELECT d.*, u.name as uploader_name, u.email as uploader_email 
                           FROM documents d 
                           LEFT JOIN users u ON d.uploaded_by = u.id 
                           WHERE d.id = ?");
    $stmt->execute([$document_id]);
    $document = $stmt->fetch();
    
    if (!$document) {
        header('Location: ' . ($user_role === 'admin' ? 'admin_dashboard.php' : 
                               ($user_role === 'user' ? 'user_dashboard.php' : 'index.php')));
        exit();
    }
    
    // Check access permissions for private documents
    if ($document['visibility'] === 'Private') {
        // Only logged-in admin or the uploader can view private documents
        if (!$user_id || ($user_role !== 'admin' && $document['uploaded_by'] != $user_id)) {
            header('Location: ' . ($user_role === 'user' ? 'user_dashboard.php' : 'index.php'));
            exit();
        }
    }
    
} catch (PDOException $e) {
    header('Location: ' . ($user_role === 'admin' ? 'admin_dashboard.php' : 
                           ($user_role === 'user' ? 'user_dashboard.php' : 'index.php')));
    exit();
}

// Fetch all versions of this document
try {
    $versions_stmt = $pdo->prepare("SELECT v.*, u.name as version_uploader_name 
                                    FROM versions v 
                                    LEFT JOIN users u ON v.uploaded_by = u.id 
                                    WHERE v.document_id = ? 
                                    ORDER BY v.version_number DESC");
    $versions_stmt->execute([$document_id]);
    $versions = $versions_stmt->fetchAll();
} catch (PDOException $e) {
    $versions = [];
}

function formatFileSize($size) {
    if ($size < 1024) return $size . ' B';
    if ($size < 1048576) return round($size / 1024, 2) . ' KB';
    if ($size < 1073741824) return round($size / 1048576, 2) . ' MB';
    return round($size / 1073741824, 2) . ' GB';
}

function getFileIcon($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $icons = [
        'pdf' => 'bi-file-earmark-pdf',
        'doc' => 'bi-file-earmark-word',
        'docx' => 'bi-file-earmark-word',
        'txt' => 'bi-file-earmark-text',
        'jpg' => 'bi-file-earmark-image',
        'jpeg' => 'bi-file-earmark-image',
        'png' => 'bi-file-earmark-image',
        'gif' => 'bi-file-earmark-image',
        'xlsx' => 'bi-file-earmark-excel',
        'xls' => 'bi-file-earmark-excel',
        'ppt' => 'bi-file-earmark-ppt',
        'pptx' => 'bi-file-earmark-ppt',
        'zip' => 'bi-file-earmark-zip',
        'rar' => 'bi-file-earmark-zip'
    ];
    return $icons[$extension] ?? 'bi-file-earmark';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($document['title']); ?> - LIMSys</title>
    
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
        
        .document-header {
            background: var(--primary-blue);
            color: white;
            border-radius: 8px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .document-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .version-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .version-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        .download-btn {
            background: var(--primary-blue);
            border: none;
            color: white;
            border-radius: 6px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: background-color 0.2s ease;
        }
        
        .download-btn:hover {
            background: var(--primary-blue-dark);
            color: white;
        }
        
        .visibility-badge {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
        }
        
        .version-badge {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            font-weight: 600;
            border-radius: 15px;
            padding: 0.4rem 0.8rem;
        }
        
        .latest-version {
            border-left: 4px solid #28a745;
        }
        
        .document-meta {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
        }
        
        .file-icon {
            font-size: 2rem;
            color: #667eea;
        }
        
        .breadcrumb {
            background: none;
            padding: 0;
        }
        
        .breadcrumb-item a {
            color: #667eea;
            text-decoration: none;
        }
        
        .breadcrumb-item a:hover {
            text-decoration: underline;
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
                        <small class="text-white-50"><?php echo ucfirst($user_role); ?> Panel</small>
                    </div>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link" href="<?php echo $user_role === 'admin' ? 'admin_dashboard.php' : 
                                                           ($user_role === 'user' ? 'user_dashboard.php' : 'guest_dashboard.php'); ?>">
                            <i class="bi bi-speedometer2 me-2"></i>Dashboard
                        </a>
                        <?php if ($user_role !== 'guest'): ?>
                        <a class="nav-link" href="upload.php">
                            <i class="bi bi-cloud-upload me-2"></i>Upload Document
                        </a>
                        <?php endif; ?>
                        <a class="nav-link active" href="#document-details">
                            <i class="bi bi-file-earmark-text me-2"></i>Document Details
                        </a>
                        <a class="nav-link" href="#versions-section">
                            <i class="bi bi-clock-history me-2"></i>Version History
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
                    <!-- Breadcrumb -->
                    <nav aria-label="breadcrumb" class="mb-4">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="<?php echo $user_role === 'admin' ? 'admin_dashboard.php' : 
                                                 ($user_role === 'user' ? 'user_dashboard.php' : 'guest_dashboard.php'); ?>">
                                    Dashboard
                                </a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">
                                <?php echo htmlspecialchars($document['title']); ?>
                            </li>
                        </ol>
                    </nav>
                    
                    <!-- Document Header -->
                    <div class="document-header">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <div class="d-flex align-items-center mb-3">
                                    <i class="<?php echo getFileIcon($document['filename']); ?> fs-1 me-3"></i>
                                    <div>
                                        <h1 class="h2 mb-1"><?php echo htmlspecialchars($document['title']); ?></h1>
                                        <div class="d-flex align-items-center">
                                            <span class="visibility-badge bg-<?php echo $document['visibility'] === 'Public' ? 'success' : 'warning'; ?>">
                                                <?php echo $document['visibility'] === 'Public' ? '🌐 Public' : '🔒 Private'; ?>
                                            </span>
                                            <span class="ms-3 opacity-75">
                                                <i class="bi bi-file-earmark me-1"></i>
                                                <?php echo htmlspecialchars($document['original_filename']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <a href="download.php?id=<?php echo $document['id']; ?>" class="btn btn-light btn-lg">
                                    <i class="bi bi-download me-2"></i>Download Latest
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Document Details -->
                    <div id="document-details" class="document-card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-info-circle me-2"></i>Document Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <?php if (!empty($document['description'])): ?>
                                    <div class="mb-4">
                                        <h6 class="text-muted mb-2">Description</h6>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($document['description'])); ?></p>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="document-meta mb-3">
                                                <h6 class="text-muted mb-3">Upload Information</h6>
                                                <div class="mb-2">
                                                    <strong>Uploaded by:</strong>
                                                    <span class="ms-2"><?php echo htmlspecialchars($document['uploader_name']); ?></span>
                                                </div>
                                                <div class="mb-2">
                                                    <strong>Upload date:</strong>
                                                    <span class="ms-2"><?php echo date('F j, Y \a\t g:i A', strtotime($document['created_at'])); ?></span>
                                                </div>
                                                <div class="mb-2">
                                                    <strong>File size:</strong>
                                                    <span class="ms-2"><?php echo formatFileSize($document['file_size']); ?></span>
                                                </div>
                                                <div>
                                                    <strong>Total versions:</strong>
                                                    <span class="ms-2"><?php echo count($versions); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="document-meta">
                                                <h6 class="text-muted mb-3">Document Details</h6>
                                                <div class="mb-2">
                                                    <strong>Document ID:</strong>
                                                    <span class="ms-2">#<?php echo $document['id']; ?></span>
                                                </div>
                                                <div class="mb-2">
                                                    <strong>Original filename:</strong>
                                                    <span class="ms-2"><?php echo htmlspecialchars($document['original_filename']); ?></span>
                                                </div>
                                                <div class="mb-2">
                                                    <strong>Visibility:</strong>
                                                    <span class="ms-2">
                                                        <span class="badge bg-<?php echo $document['visibility'] === 'Public' ? 'success' : 'warning'; ?>">
                                                            <?php echo $document['visibility']; ?>
                                                        </span>
                                                    </span>
                                                </div>
                                                <div>
                                                    <strong>Last modified:</strong>
                                                    <span class="ms-2"><?php echo date('M j, Y', strtotime($document['created_at'])); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 text-center">
                                    <i class="<?php echo getFileIcon($document['filename']); ?> file-icon mb-3"></i>
                                    <h6 class="text-muted">File Type</h6>
                                    <p class="text-uppercase fw-bold"><?php echo pathinfo($document['filename'], PATHINFO_EXTENSION); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Version History -->
                    <div id="versions-section" class="document-card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-clock-history me-2"></i>Version History
                            </h5>
                            <span class="badge bg-primary"><?php echo count($versions); ?> Version<?php echo count($versions) !== 1 ? 's' : ''; ?></span>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($versions)): ?>
                                <?php foreach ($versions as $index => $version): ?>
                                <div class="version-card <?php echo $index === 0 ? 'latest-version' : ''; ?>">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-center">
                                                    <i class="<?php echo getFileIcon($version['filename']); ?> fs-4 me-3 text-primary"></i>
                                                    <div>
                                                        <div class="d-flex align-items-center mb-1">
                                                            <span class="version-badge me-2">
                                                                Version <?php echo $version['version_number']; ?>
                                                            </span>
                                                            <?php if ($index === 0): ?>
                                                                <span class="badge bg-success">Latest</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($version['filename']); ?></h6>
                                                        <small class="text-muted">
                                                            <?php echo formatFileSize($version['file_size']); ?> •
                                                            Uploaded by <?php echo htmlspecialchars($version['version_uploader_name']); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <small class="text-muted">
                                                    <i class="bi bi-calendar me-1"></i>
                                                    <?php echo date('M j, Y \a\t g:i A', strtotime($version['created_at'])); ?>
                                                </small>
                                            </div>
                                            <div class="col-md-2 text-end">
                                                <a href="download.php?version_id=<?php echo $version['id']; ?>" 
                                                   class="btn download-btn btn-sm">
                                                    <i class="bi bi-download me-1"></i>Download
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="bi bi-clock-history fs-1"></i>
                                    <h6 class="mt-3">No Version History</h6>
                                    <p>This document doesn't have any version history yet.</p>
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
        
        // Add copy to clipboard functionality for document ID
        document.addEventListener('DOMContentLoaded', function() {
            const docId = document.querySelector('#document-details .card-body').innerHTML;
            // You can add clipboard functionality here if needed
        });
    </script>
</body>
</html>
