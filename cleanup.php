<?php
/**
 * اسکریپت پاک‌سازی خودکار
 * این فایل را می‌توانید با cron job اجرا کنید
 */

// چک نصب
if (!file_exists('installed.lock')) {
    die('سیستم نصب نشده است');
}

require_once 'config/db.php';

echo "شروع پاک‌سازی خودکار...\n";

try {
    $pdo->beginTransaction();
    
    // 1. حذف پیام‌های قدیمی‌تر از 90 روز
    $stmt = $pdo->prepare("
        DELETE FROM messages 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
    ");
    $stmt->execute();
    $deleted_messages = $stmt->rowCount();
    echo "پیام‌های حذف شده: $deleted_messages\n";
    
    // 2. حذف سیگنال‌های WebRTC قدیمی‌تر از 1 روز
    $stmt = $pdo->prepare("
        DELETE FROM webrtc_signals 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
    ");
    $stmt->execute();
    $deleted_signals = $stmt->rowCount();
    echo "سیگنال‌های حذف شده: $deleted_signals\n";
    
    // 3. حذف فایل‌های یتیم (فایل‌هایی که پیام مربوطه حذف شده)
    $stmt = $pdo->query("
        SELECT cf.file_path 
        FROM chat_files cf
        LEFT JOIN messages m ON (
            (m.sender_id = cf.sender_id AND m.receiver_id = cf.receiver_id) OR
            (m.sender_id = cf.receiver_id AND m.receiver_id = cf.sender_id)
        ) AND m.message LIKE CONCAT('%', cf.original_name, '%')
        WHERE m.id IS NULL
    ");
    $orphan_files = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($orphan_files as $file_path) {
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    // حذف رکوردهای فایل یتیم از دیتابیس
    $stmt = $pdo->prepare("
        DELETE cf FROM chat_files cf
        LEFT JOIN messages m ON (
            (m.sender_id = cf.sender_id AND m.receiver_id = cf.receiver_id) OR
            (m.sender_id = cf.receiver_id AND m.receiver_id = cf.sender_id)
        ) AND m.message LIKE CONCAT('%', cf.original_name, '%')
        WHERE m.id IS NULL
    ");
    $stmt->execute();
    $deleted_files = $stmt->rowCount();
    echo "فایل‌های یتیم حذف شده: $deleted_files\n";
    
    // 4. پاک‌سازی recent_chats های قدیمی (بیش از 30 روز بدون فعالیت)
    $stmt = $pdo->prepare("
        DELETE FROM recent_chats 
        WHERE updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $deleted_chats = $stmt->rowCount();
    echo "چت‌های قدیمی حذف شده: $deleted_chats\n";
    
    // 5. بهینه‌سازی جداول
    $tables = ['messages', 'webrtc_signals', 'chat_files', 'recent_chats', 'user_blocks'];
    foreach ($tables as $table) {
        $pdo->exec("OPTIMIZE TABLE $table");
    }
    echo "جداول بهینه‌سازی شدند\n";
    
    $pdo->commit();
    echo "پاک‌سازی با موفقیت انجام شد!\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "خطا در پاک‌سازی: " . $e->getMessage() . "\n";
}

echo "پایان پاک‌سازی\n";
?>