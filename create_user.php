<?php
/**
 * User Creation Script for LIMSys
 * Use this to create a properly hashed user account
 */

require_once 'db.php';

// Configuration - Change these values
$name = 'Admin User';
$email = 'admin@limsys.com';
$password = 'admin123';  // Change this to your desired password
$role = 'admin';  // Change to 'user' or 'guest' as needed

try {
    // Check if user already exists
    $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check_stmt->execute([$email]);
    
    if ($check_stmt->fetch()) {
        echo "User with email '$email' already exists!\n";
        exit;
    }
    
    // Hash the password properly
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert the user
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())");
    
    if ($stmt->execute([$name, $email, $hashed_password, $role])) {
        echo "User created successfully!\n";
        echo "Email: $email\n";
        echo "Password: $password\n";
        echo "Role: $role\n";
        echo "\nYou can now login with these credentials.\n";
    } else {
        echo "Error creating user.\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>
