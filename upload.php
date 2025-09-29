<?php
/**
 * LIMSys Document Upload Page
 * Handles document upload with versioning for Admin and User roles
 */

session_start();

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'user'])) {
    header('Location: login.php');
    exit();
}

require_once 'db.php';

// Include Composer autoloader for text extraction libraries
if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
}

// Include fallback classes
require_once 'src/TextExtractorFallback.php';
require_once 'src/SimilarityCalculator.php';

// Try to use full-featured classes, fallback to simple versions
if (class_exists('LIMSys\TextExtractor')) {
    // Use the full-featured version if available
} else {
    // Alias the fallback class
    class_alias('LIMSys\TextExtractorFallback', 'LIMSys\TextExtractor');
}

$message = '';
$message_type = '';
$user_id = $_SESSION['user_id'];

// Create uploads directory if it doesn't exist
$upload_dir = 'uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $visibility = $_POST['visibility'] ?? 'Private';
    
    // Validation
    if (empty($title)) {
        $message = 'Please enter a document title.';
        $message_type = 'danger';
    } elseif (empty($_FILES['document_file']['name'])) {
        $message = 'Please select a file to upload.';
        $message_type = 'danger';
    } elseif (!in_array($visibility, ['Public', 'Private'])) {
        $message = 'Invalid visibility setting.';
        $message_type = 'danger';
    } else {
        $file = $_FILES['document_file'];
        $file_error = $file['error'];
        $file_size = $file['size'];
        $file_tmp = $file['tmp_name'];
        $original_filename = $file['name'];
        
        // File validation
        $max_file_size = 50 * 1024 * 1024; // 50MB
        $allowed_extensions = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'xlsx', 'xls', 'ppt', 'pptx', 'zip', 'rar'];
        
        $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
        
        if ($file_error !== UPLOAD_ERR_OK) {
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE => 'File is too large (server limit)',
                UPLOAD_ERR_FORM_SIZE => 'File is too large (form limit)',
                UPLOAD_ERR_PARTIAL => 'File upload was interrupted',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'File upload blocked by extension'
            ];
            $message = $upload_errors[$file_error] ?? 'Unknown upload error';
            $message_type = 'danger';
        } elseif ($file_size > $max_file_size) {
            $message = 'File is too large. Maximum size is 50MB.';
            $message_type = 'danger';
        } elseif (!in_array($file_extension, $allowed_extensions)) {
            $message = 'File type not allowed. Allowed types: ' . implode(', ', $allowed_extensions);
            $message_type = 'danger';
        } else {
            try {
                // Generate unique filename
                $unique_filename = uniqid('doc_') . '_' . time() . '.' . $file_extension;
                $file_path = $upload_dir . $unique_filename;
                
                // Move uploaded file
                if (move_uploaded_file($file_tmp, $file_path)) {
                    
                    // Extract text from the uploaded file for AI comparison
                    $extracted_text = '';
                    $supportedFormats = ['pdf', 'doc', 'docx', 'txt'];
                    
                    if (in_array($file_extension, $supportedFormats)) {
                        if (class_exists('LIMSys\TextExtractor')) {
                            $extracted_text = \LIMSys\TextExtractor::extractText($file_path, $file_extension);
                        } else {
                            $extracted_text = \LIMSys\TextExtractorFallback::extractText($file_path, $file_extension);
                        }
                    }
                    
                    // AI-powered version detection
                    $is_new_version = false;
                    $existing_document_id = null;
                    $similarity_percentage = 0;
                    $next_version_number = 1;
                    
                    if (!empty($extracted_text)) {
                        // Find existing documents by the same user with similar titles or content
                        $similar_docs_stmt = $pdo->prepare("
                            SELECT d.id, d.title, d.extracted_text, MAX(v.version_number) as latest_version
                            FROM documents d
                            LEFT JOIN versions v ON d.id = v.document_id
                            WHERE d.uploaded_by = ? 
                            AND d.extracted_text IS NOT NULL 
                            AND d.extracted_text != ''
                            GROUP BY d.id
                            ORDER BY d.created_at DESC
                        ");
                        $similar_docs_stmt->execute([$user_id]);
                        $existing_documents = $similar_docs_stmt->fetchAll();
                        
                        $best_similarity = 0;
                        $best_match_id = null;
                        
                        // Compare with existing documents
                        foreach ($existing_documents as $existing_doc) {
                            if (!empty($existing_doc['extracted_text'])) {
                                $similarity = \LIMSys\SimilarityCalculator::getSimilarityPercentage(
                                    $extracted_text, 
                                    $existing_doc['extracted_text']
                                );
                                
                                // Also check title similarity for better matching
                                $title_similarity = \LIMSys\SimilarityCalculator::getSimilarityPercentage(
                                    strtolower($title), 
                                    strtolower($existing_doc['title'])
                                );
                                
                                // Combined similarity with higher weight on content
                                $combined_similarity = ($similarity * 0.8) + ($title_similarity * 0.2);
                                
                                if ($combined_similarity > $best_similarity) {
                                    $best_similarity = $combined_similarity;
                                    $best_match_id = $existing_doc['id'];
                                    $next_version_number = $existing_doc['latest_version'] + 1;
                                }
                            }
                        }
                        
                        // Determine if this should be a new version (85% threshold)
                        if ($best_similarity >= 85) {
                            $is_new_version = true;
                            $existing_document_id = $best_match_id;
                            $similarity_percentage = round($best_similarity, 1);
                        }
                    }
                    
                    // Start transaction
                    $pdo->beginTransaction();
                    
                    if ($is_new_version && $existing_document_id) {
                        // Store as new version of existing document
                        $version_stmt = $pdo->prepare("INSERT INTO versions (document_id, version_number, filename, file_path, file_size, uploaded_by, created_at) 
                                                       VALUES (?, ?, ?, ?, ?, ?, NOW())");
                        
                        $version_stmt->execute([
                            $existing_document_id,
                            $next_version_number,
                            $unique_filename,
                            $file_path,
                            $file_size,
                            $user_id
                        ]);
                        
                        // Update document's extracted text and metadata
                        $update_doc_stmt = $pdo->prepare("UPDATE documents SET 
                            extracted_text = ?, 
                            filename = ?, 
                            original_filename = ?, 
                            file_path = ?, 
                            file_size = ?,
                            updated_at = NOW()
                            WHERE id = ?");
                        
                        $update_doc_stmt->execute([
                            $extracted_text,
                            $unique_filename,
                            $original_filename,
                            $file_path,
                            $file_size,
                            $existing_document_id
                        ]);
                        
                        $document_id = $existing_document_id;
                        $message = "Stored as new version (Version {$next_version_number}) - {$similarity_percentage}% similarity detected with existing document.";
                        $message_type = 'info';
                        
                    } else {
                        // Store as completely new document
                        $stmt = $pdo->prepare("INSERT INTO documents (title, description, filename, original_filename, file_path, file_size, visibility, uploaded_by, extracted_text, created_at) 
                                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                        
                        $stmt->execute([
                            $title,
                            $description,
                            $unique_filename,
                            $original_filename,
                            $file_path,
                            $file_size,
                            $visibility,
                            $user_id,
                            $extracted_text
                        ]);
                        
                        $document_id = $pdo->lastInsertId();
                        
                        // Insert initial version record
                        $version_stmt = $pdo->prepare("INSERT INTO versions (document_id, version_number, filename, file_path, file_size, uploaded_by, created_at) 
                                                       VALUES (?, 1, ?, ?, ?, ?, NOW())");
                        
                        $version_stmt->execute([
                            $document_id,
                            $unique_filename,
                            $file_path,
                            $file_size,
                            $user_id
                        ]);
                        
                        if ($similarity_percentage > 0) {
                            $message = "Stored as new document - {$similarity_percentage}% similarity detected but below 85% threshold.";
                        } else {
                            $message = 'Document uploaded successfully as new document!';
                        }
                        $message_type = 'success';
                    }
                    
                    // Commit transaction
                    $pdo->commit();
                    
                    // Redirect to appropriate dashboard after successful upload
                    header("refresh:3;url=" . ($_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : 'user_dashboard.php'));
                } else {
                    throw new Exception('Failed to move uploaded file');
                }
            } catch (Exception $e) {
                // Rollback transaction
                if ($pdo->inTransaction()) {
                    $pdo->rollback();
                }
                
                // Delete uploaded file if it exists
                if (isset($file_path) && file_exists($file_path)) {
                    unlink($file_path);
                }
                
                $message = 'Error uploading document: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Document - LIMSys</title>
    
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
        
        .upload-card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        }
        
        .upload-header {
            background: var(--primary-blue);
            color: white;
            border-radius: 8px 8px 0 0;
            padding: 2rem;
        }
        
        .file-drop-area {
            border: 2px dashed #D1D5DB;
            border-radius: 8px;
            padding: 3rem 2rem;
            text-align: center;
            background: var(--neutral-light);
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .file-drop-area:hover,
        .file-drop-area.dragover {
            border-color: var(--primary-blue);
            background: var(--primary-blue-light);
        }
        
        .file-drop-area.file-selected {
            border-color: #10B981;
            background: #D1FAE5;
        }
        
        .btn-primary-custom {
            background: var(--primary-blue);
            border: none;
            font-weight: 500;
            padding: 0.75rem 2rem;
            border-radius: 6px;
            transition: background-color 0.2s ease;
        }
        
        .btn-primary-custom:hover {
            background: var(--primary-blue-dark);
            color: white;
        }
        
        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px var(--primary-blue-light);
        }
        
        .file-info {
            background: var(--accent-yellow-light);
            border: 1px solid var(--accent-yellow);
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            display: none;
        }
        
        .allowed-types {
            font-size: 0.875rem;
            color: var(--neutral-gray);
            margin-top: 0.5rem;
        }

        /* Additional styling */
        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-success {
            background-color: #10B981;
            border-color: #10B981;
        }

        .btn-outline-secondary {
            color: var(--neutral-gray);
            border-color: var(--neutral-gray);
        }

        .btn-outline-secondary:hover {
            background-color: var(--neutral-gray);
            border-color: var(--neutral-gray);
        }

        /* Alert styling */
        .alert-success {
            background-color: #D1FAE5;
            border-color: #10B981;
            color: #065F46;
        }

        .alert-danger {
            background-color: var(--accent-red-light);
            border-color: var(--accent-red);
            color: var(--accent-red);
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
                        <small class="text-white-50"><?php echo ucfirst($_SESSION['role']); ?> Panel</small>
                    </div>
                    
                    <nav class="nav flex-column">

                        <a class="nav-link" href="<?php echo $_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : 'user_dashboard.php'; ?>">
                            <i class="bi bi-speedometer2 me-2"></i>Dashboard
                        </a>
                        <a class="nav-link active" href="upload.php">
                            <i class="bi bi-cloud-upload me-2"></i>Upload Document
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
                            <h1 class="h3">Upload Document</h1>
                            <p class="text-muted mb-0">Add a new document to your collection</p>
                        </div>
                        <a href="<?php echo $_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : 'user_dashboard.php'; ?>" 
                           class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>
                    
                    <!-- Alert Messages -->
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Upload Form -->
                    <div class="row justify-content-center">
                        <div class="col-lg-8">
                            <div class="card upload-card">
                                <div class="upload-header text-center">
                                    <i class="bi bi-cloud-upload" style="font-size: 3rem;"></i>
                                    <h3 class="mt-3 mb-2">Upload New Document</h3>
                                    <p class="mb-0 opacity-75">Share your documents with the LIMSys community</p>
                                    <div class="mt-3 p-2 bg-light bg-opacity-10 rounded">
                                        <small class="d-block">
                                            <i class="bi bi-cpu me-1"></i>
                                            <strong>AI-Powered Version Control:</strong> Our system automatically detects document similarities and manages versions intelligently.
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="card-body p-4">
                                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                                        <!-- Document Title -->
                                        <div class="mb-4">
                                            <label for="title" class="form-label fw-bold">
                                                <i class="bi bi-card-heading me-2"></i>Document Title *
                                            </label>
                                            <input type="text" 
                                                   class="form-control form-control-lg" 
                                                   id="title" 
                                                   name="title" 
                                                   required 
                                                   value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                                                   placeholder="Enter a descriptive title for your document">
                                        </div>
                                        
                                        <!-- Description -->
                                        <div class="mb-4">
                                            <label for="description" class="form-label fw-bold">
                                                <i class="bi bi-card-text me-2"></i>Description
                                            </label>
                                            <textarea class="form-control" 
                                                      id="description" 
                                                      name="description" 
                                                      rows="4" 
                                                      placeholder="Provide a detailed description of your document (optional)"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                        </div>
                                        
                                        <!-- File Upload -->
                                        <div class="mb-4">
                                            <label for="document_file" class="form-label fw-bold">
                                                <i class="bi bi-file-earmark-arrow-up me-2"></i>Select File *
                                            </label>
                                            <div class="file-drop-area" id="fileDropArea">
                                                <i class="bi bi-cloud-upload fs-1 text-muted"></i>
                                                <h5 class="mt-3 mb-2">Drag & drop your file here</h5>
                                                <p class="text-muted mb-3">or click to browse</p>
                                                <input type="file" 
                                                       class="form-control d-none" 
                                                       id="document_file" 
                                                       name="document_file" 
                                                       required 
                                                       accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.gif,.xlsx,.xls,.ppt,.pptx,.zip,.rar">
                                                <button type="button" class="btn btn-outline-primary" onclick="event.stopPropagation(); document.getElementById('document_file').click();">
                                                    <i class="bi bi-folder2-open me-2"></i>Choose File
                                                </button>
                                            </div>
                                            <div class="file-info" id="fileInfo">
                                                <div class="d-flex align-items-center">
                                                    <i class="bi bi-file-earmark text-primary me-2"></i>
                                                    <div>
                                                        <div class="fw-bold" id="fileName"></div>
                                                        <small class="text-muted" id="fileSize"></small>
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-outline-danger ms-auto" onclick="clearFile()">
                                                        <i class="bi bi-x"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="allowed-types">
                                                <strong>Allowed file types:</strong> PDF, DOC, DOCX, TXT, JPG, JPEG, PNG, GIF, XLSX, XLS, PPT, PPTX, ZIP, RAR<br>
                                                <strong>Maximum file size:</strong> 50MB<br>
                                                <div class="mt-2 p-2 bg-info bg-opacity-10 rounded">
                                                    <small>
                                                        <i class="bi bi-robot me-1 text-info"></i>
                                                        <strong>AI Analysis:</strong> PDF, DOC, DOCX, and TXT files will be analyzed for content similarity with your existing documents.
                                                        If similarity ≥ 85%, the file will be stored as a new version; otherwise, it becomes a new document.
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Visibility -->
                                        <div class="mb-4">
                                            <label for="visibility" class="form-label fw-bold">
                                                <i class="bi bi-eye me-2"></i>Visibility
                                            </label>
                                            <select class="form-select form-select-lg" id="visibility" name="visibility">
                                                <option value="Private" <?php echo ($_POST['visibility'] ?? 'Private') === 'Private' ? 'selected' : ''; ?>>
                                                    🔒 Private - Only you can access this document
                                                </option>
                                                <option value="Public" <?php echo ($_POST['visibility'] ?? '') === 'Public' ? 'selected' : ''; ?>>
                                                    🌐 Public - Everyone can view and download this document
                                                </option>
                                            </select>
                                        </div>
                                        
                                        <!-- Submit Button -->
                                        <div class="d-grid">
                                            <button type="submit" name="upload_document" class="btn btn-primary-custom btn-lg">
                                                <i class="bi bi-cloud-upload me-2"></i>Upload Document
                                            </button>
                                        </div>
                                    </form>
                                </div>
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
        // File handling functionality
        const fileInput = document.getElementById('document_file');
        const fileDropArea = document.getElementById('fileDropArea');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        
        // File size formatter
        function formatFileSize(size) {
            if (size < 1024) return size + ' B';
            if (size < 1048576) return Math.round(size / 1024 * 100) / 100 + ' KB';
            if (size < 1073741824) return Math.round(size / 1048576 * 100) / 100 + ' MB';
            return Math.round(size / 1073741824 * 100) / 100 + ' GB';
        }
        
        // Handle file selection
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                fileName.textContent = file.name;
                fileSize.textContent = formatFileSize(file.size);
                fileInfo.style.display = 'block';
                fileDropArea.classList.add('file-selected');
            }
        });
        
        // Clear file selection
        function clearFile() {
            fileInput.value = '';
            fileInfo.style.display = 'none';
            fileDropArea.classList.remove('file-selected');
        }
        
        // Drag and drop functionality  
        fileDropArea.addEventListener('click', function(e) {
            // Only trigger file input if clicking the drop area itself, not the button
            if (e.target === this || e.target.closest('button') === null) {
                fileInput.click();
            }
        });
        
        fileDropArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });
        
        fileDropArea.addEventListener('dragleave', function() {
            this.classList.remove('dragover');
        });
        
        fileDropArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                fileInput.dispatchEvent(new Event('change'));
            }
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Form validation
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            const title = document.getElementById('title').value.trim();
            const file = fileInput.files[0];
            
            console.log('Form submit attempt - Title:', title, 'File:', file);
            
            if (!title) {
                e.preventDefault();
                alert('Please enter a document title.');
                return false;
            }
            
            if (!file) {
                e.preventDefault();
                alert('Please select a file to upload.');
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Uploading...';
                
                // Prevent double submission
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-cloud-upload me-2"></i>Upload Document';
                }, 3000);
            }
            
            return true;
        });
    </script>
</body>
</html>
