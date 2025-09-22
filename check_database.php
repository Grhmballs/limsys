<?php
/**
 * Database Structure Checker for LIMSys
 * Use this to verify your database setup
 */

require_once 'db.php';

echo "<h2>LIMSys Database Structure Check</h2>";

try {
    // Check if users table exists and get its structure
    echo "<h3>Users Table Structure:</h3>";
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll();
    
    if ($columns) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . $column['Field'] . "</td>";
            echo "<td>" . $column['Type'] . "</td>";
            echo "<td>" . $column['Null'] . "</td>";
            echo "<td>" . $column['Key'] . "</td>";
            echo "<td>" . $column['Default'] . "</td>";
            echo "<td>" . $column['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>Users table not found!</p>";
    }
    
    // Check existing users
    echo "<h3>Existing Users:</h3>";
    $users_stmt = $pdo->query("SELECT id, name, email, role, status, created_at FROM users");
    $users = $users_stmt->fetchAll();
    
    if ($users) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . $user['id'] . "</td>";
            echo "<td>" . $user['name'] . "</td>";
            echo "<td>" . $user['email'] . "</td>";
            echo "<td>" . $user['role'] . "</td>";
            echo "<td>" . $user['status'] . "</td>";
            echo "<td>" . $user['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No users found in the database.</p>";
    }
    
    // Test a sample password hash
    echo "<h3>Password Hash Test:</h3>";
    $test_password = "admin123";
    $hash = password_hash($test_password, PASSWORD_DEFAULT);
    echo "<p><strong>Original password:</strong> $test_password</p>";
    echo "<p><strong>Hashed password:</strong> $hash</p>";
    echo "<p><strong>Verification test:</strong> " . (password_verify($test_password, $hash) ? "✅ PASS" : "❌ FAIL") . "</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database error: " . $e->getMessage() . "</p>";
}
?>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    table { margin: 10px 0; }
    th, td { padding: 8px 12px; text-align: left; }
    th { background: #f0f0f0; }
</style>
