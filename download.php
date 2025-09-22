<?php
/**
 * LIMSys Document Download Handler
 * Handles secure file downloads with access control
 */

session_start();

// Users don't need to be logged in to download public documents
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['role'] ?? 'guest';

require_once 'db.php';

// Check if downloading by document ID or version ID
$document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$version_id = isset($_GET['version_id']) ? (int)$_GET['version_id'] : 0;

if (!$document_id && !$version_id) {
    http_response_code(400);
    die('Invalid request');
}

try {
    if ($version_id) {
        // Download specific version
        $stmt = $pdo->prepare("SELECT v.*, d.title, d.visibility, d.uploaded_by 
                               FROM versions v 
                               JOIN documents d ON v.document_id = d.id 
                               WHERE v.id = ?");
        $stmt->execute([$version_id]);
        $file_data = $stmt->fetch();
        
        if (!$file_data) {
            http_response_code(404);
            die('Version not found');
        }
        
        $file_path = $file_data['file_path'];
        $original_name = "v{$file_data['version_number']}_{$file_data['title']}";
        $document_visibility = $file_data['visibility'];
        $document_owner = $file_data['uploaded_by'];
        
    } else {
        // Download latest version of document
        $stmt = $pdo->prepare("SELECT d.*, v.file_path, v.version_number 
                               FROM documents d 
                               LEFT JOIN versions v ON d.id = v.document_id 
                               WHERE d.id = ? 
                               ORDER BY v.version_number DESC 
                               LIMIT 1");
        $stmt->execute([$document_id]);
        $file_data = $stmt->fetch();
        
        if (!$file_data) {
            http_response_code(404);
            die('Document not found');
        }
        
        $file_path = $file_data['file_path'] ?? $file_data['file_path'];
        $original_name = $file_data['original_filename'] ?? $file_data['title'];
        $document_visibility = $file_data['visibility'];
        $document_owner = $file_data['uploaded_by'];
    }
    
    // Check access permissions
    if ($document_visibility === 'Private') {
        // Only logged-in admin or the uploader can download private documents
        if (!$user_id || ($user_role !== 'admin' && $document_owner != $user_id)) {
            http_response_code(403);
            die('Access denied');
        }
    }
    // Public documents can be downloaded by anyone
    
    // Check if file exists
    if (!file_exists($file_path)) {
        http_response_code(404);
        die('File not found on server');
    }
    
    // Get file info
    $file_size = filesize($file_path);
    $file_extension = pathinfo($file_path, PATHINFO_EXTENSION);
    
    // Clean the filename for download
    $clean_filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $original_name);
    if (!pathinfo($clean_filename, PATHINFO_EXTENSION)) {
        $clean_filename .= '.' . $file_extension;
    }
    
    // Set appropriate headers for file download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $clean_filename . '"');
    header('Content-Length: ' . $file_size);
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Clear any output buffer
    if (ob_get_level()) {
        ob_clean();
    }
    
    // Read and output the file
    $handle = fopen($file_path, 'rb');
    if ($handle === false) {
        http_response_code(500);
        die('Error reading file');
    }
    
    while (!feof($handle)) {
        echo fread($handle, 8192);
        flush();
    }
    
    fclose($handle);
    
} catch (Exception $e) {
    error_log("Download error: " . $e->getMessage());
    http_response_code(500);
    die('Download failed');
}
?>
