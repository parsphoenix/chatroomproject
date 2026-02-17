<?php
/**
 * Script to update database schema for new features.
 * Run this if you are upgrading an existing installation.
 */

// Define path to db config
$configFile = __DIR__ . '/config/db.php';

if (!file_exists($configFile)) {
    die("Error: Config file not found at $configFile. Please install the application first using install.php.");
}

require_once $configFile;

echo "Starting database migration...\n";

try {
    // 1. Add columns to messages table
    echo "Updating 'messages' table...\n";
    
    // Check if 'status' column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM messages LIKE 'status'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE messages ADD COLUMN status ENUM('sent', 'delivered', 'read') DEFAULT 'sent' AFTER is_read");
        echo "- Added 'status' column.\n";
    } else {
        echo "- 'status' column already exists.\n";
    }

    // Check if 'type' column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM messages LIKE 'type'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE messages ADD COLUMN type VARCHAR(20) DEFAULT 'text' AFTER message");
        echo "- Added 'type' column.\n";
    } else {
        echo "- 'type' column already exists.\n";
    }

    // Check if 'metadata' column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM messages LIKE 'metadata'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE messages ADD COLUMN metadata TEXT DEFAULT NULL AFTER type");
        echo "- Added 'metadata' column.\n";
    } else {
        echo "- 'metadata' column already exists.\n";
    }

    // 2. Add columns to users table
    echo "Updating 'users' table...\n";

    // Check if 'preferred_bitrate' column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'preferred_bitrate'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN preferred_bitrate INT DEFAULT 500 AFTER language_preference");
        echo "- Added 'preferred_bitrate' column.\n";
    } else {
        echo "- 'preferred_bitrate' column already exists.\n";
    }
    
    // Check if 'enable_notifications' column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'enable_notifications'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN enable_notifications BOOLEAN DEFAULT TRUE AFTER show_online_status");
        echo "- Added 'enable_notifications' column.\n";
    } else {
        echo "- 'enable_notifications' column already exists.\n";
    }

    echo "Migration completed successfully!\n";

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage() . "\n");
}
?>
