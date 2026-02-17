<?php
/**
 * Script to add remember_token column to users table.
 */

$configFile = __DIR__ . '/config/db.php';

if (!file_exists($configFile)) {
    die("Error: Config file not found. Please ensure the app is installed.");
}

require_once $configFile;

echo "Adding 'remember_token' column...\n";

try {
    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'remember_token'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN remember_token VARCHAR(255) DEFAULT NULL AFTER password_hash");
        echo "- Added 'remember_token' column.\n";
    } else {
        echo "- 'remember_token' column already exists.\n";
    }
    
    echo "Done.\n";

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage() . "\n");
}
?>
