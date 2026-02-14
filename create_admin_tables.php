<?php
/**
 * اسکریپت ایجاد جداول ادمین (بدون نیاز به اتصال دیتابیس)
 */

echo "<h2>📋 SQL Commands برای ایجاد جداول ادمین</h2>";
echo "<p>کدهای زیر را در phpMyAdmin یا MySQL اجرا کنید:</p>";

$sql_commands = [
    "-- جدول ممنوعیت کاربران",
    "CREATE TABLE IF NOT EXISTS user_bans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        banned_user_id INT NOT NULL,
        banned_by_admin_id INT NOT NULL,
        ban_reason TEXT,
        banned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_active BOOLEAN DEFAULT TRUE,
        browser_fingerprint TEXT,
        ip_address VARCHAR(45),
        INDEX idx_banned_user (banned_user_id),
        INDEX idx_active_bans (is_active),
        INDEX idx_browser_fingerprint (browser_fingerprint(100))
    );",
    
    "-- اضافه کردن فیلد نقش کاربر به جدول users",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS user_role ENUM('user', 'admin') DEFAULT 'user';",
    
    "-- تنظیم کاربر admin به عنوان ادمین",
    "UPDATE users SET user_role = 'admin' WHERE username = 'admin';",
    
    "-- جدول لاگ فعالیت‌های ادمین",
    "CREATE TABLE IF NOT EXISTS admin_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        action_type ENUM('ban_user', 'unban_user', 'view_users') NOT NULL,
        target_user_id INT,
        action_details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_admin_logs (admin_id, created_at)
    );"
];

echo "<div style='background: #f5f5f5; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
echo "<pre style='background: #333; color: #fff; padding: 15px; border-radius: 5px; overflow-x: auto;'>";

foreach ($sql_commands as $command) {
    echo htmlspecialchars($command) . "\n\n";
}

echo "</pre>";
echo "</div>";

echo "<h3>✅ مراحل اجرا:</h3>";
echo "<ol>";
echo "<li>وارد phpMyAdmin شوید</li>";
echo "<li>دیتابیس وب‌چت خود را انتخاب کنید</li>";
echo "<li>به تب SQL بروید</li>";
echo "<li>کدهای بالا را کپی و paste کنید</li>";
echo "<li>دکمه Go را بزنید</li>";
echo "<li>کاربر admin حالا دسترسی ادمین خواهد داشت</li>";
echo "</ol>";

echo "<p><strong>نکته:</strong> اگر کاربر admin وجود ندارد، ابتدا آن را ایجاد کنید.</p>";
echo "<p><a href='dashboard.php'>بازگشت به داشبورد</a></p>";
?>