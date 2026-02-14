<?php
/**
 * اسکریپت بروزرسانی دیتابیس برای سیستم ادمین
 */

// چک نصب
if (!file_exists('installed.lock')) {
    die('سیستم نصب نشده است.');
}

require_once 'config/db.php';

try {
    echo "<h2>🔄 در حال بروزرسانی دیتابیس...</h2>";
    
    // خواندن فایل SQL
    $sql_content = file_get_contents('admin_ban_system.sql');
    
    if ($sql_content === false) {
        throw new Exception('فایل SQL یافت نشد');
    }
    
    // تقسیم کوئری‌ها
    $queries = explode(';', $sql_content);
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            try {
                $pdo->exec($query);
                echo "✅ کوئری اجرا شد: " . substr($query, 0, 50) . "...<br>";
            } catch (PDOException $e) {
                echo "⚠️ خطا در کوئری: " . $e->getMessage() . "<br>";
                echo "کوئری: " . substr($query, 0, 100) . "...<br><br>";
            }
        }
    }
    
    echo "<br><h3>✅ بروزرسانی دیتابیس کامل شد!</h3>";
    echo "<p><a href='dashboard.php'>بازگشت به داشبورد</a></p>";
    
    // تنظیم کاربر admin به عنوان ادمین
    try {
        $stmt = $pdo->prepare("UPDATE users SET user_role = 'admin' WHERE username = 'admin'");
        $stmt->execute();
        echo "<p>✅ کاربر admin به عنوان ادمین تنظیم شد</p>";
    } catch (PDOException $e) {
        echo "<p>⚠️ خطا در تنظیم نقش ادمین: " . $e->getMessage() . "</p>";
    }
    
} catch (Exception $e) {
    echo "<h3>❌ خطا در بروزرسانی:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>