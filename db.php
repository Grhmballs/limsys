
<?php
/**
 * LIMSys Database Connection
 * Connects to MySQL database using PDO
 */

// Database configuration
$host = 'localhost';
$dbname = 'limsys';
$username = 'root';
$password = '';

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Set PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set default fetch mode to associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Disable emulated prepared statements for better security
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
    // echo "Database connected successfully"; // Commented out for production
    
} catch (PDOException $e) {
    // Display error message
    echo "Connection failed: " . $e->getMessage();
    
    // Log error (optional - uncomment if you want to log errors)
    // error_log("Database connection failed: " . $e->getMessage());
    
    // Stop execution on connection failure
    die();
}
?>

