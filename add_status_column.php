<?php
/**
 * Add missing 'status' column to users table
 * Run this script once to fix the database structure
 */

require_once 'db.php';

try {
    // Add the status column to the users table
    $sql = "ALTER TABLE users ADD COLUMN status ENUM('active', 'inactive', 'suspended') DEFAULT 'active'";
    $pdo->exec($sql);
    
    echo "✅ Status column added successfully!\n";
    
    // Update existing users to have 'active' status
    $update_sql = "UPDATE users SET status = 'active' WHERE status IS NULL";
    $affected = $pdo->exec($update_sql);
    
    echo "✅ Updated $affected existing users to 'active' status.\n";
    echo "\nYou can now login normally. The database structure has been fixed.\n";
    echo "\nAfter running this script, you can delete it as it's only needed once.\n";
    
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "ℹ️  Status column already exists. No changes needed.\n";
    } else {
        echo "❌ Error adding status column: " . $e->getMessage() . "\n";
    }
}
?>
