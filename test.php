<?php
/**
 * فایل تست سیستم - فقط برای توسعه‌دهندگان
 * این فایل را پس از تست حذف کنید
 */

// چک کردن پیش‌نیازها
echo "<h1>🧪 تست سیستم وب‌چت</h1>";

echo "<h2>1. بررسی نسخه PHP</h2>";
echo "نسخه PHP: " . phpversion();
if (version_compare(phpversion(), '7.4.0', '>=')) {
    echo " ✅ مناسب";
} else {
    echo " ❌ نیاز به PHP 7.4 یا بالاتر";
}

echo "<h2>2. بررسی افزونه‌های PHP</h2>";
$required_extensions = ['pdo', 'pdo_mysql', 'json', 'session'];
foreach ($required_extensions as $ext) {
    echo "- $ext: ";
    if (extension_loaded($ext)) {
        echo "✅ نصب شده<br>";
    } else {
        echo "❌ نصب نشده<br>";
    }
}

echo "<h2>3. بررسی مجوزات فایل‌ها</h2>";
$directories = ['.', 'config', 'api', 'assets'];
foreach ($directories as $dir) {
    echo "- $dir: ";
    if (is_writable($dir)) {
        echo "✅ قابل نوشتن<br>";
    } else {
        echo "❌ غیرقابل نوشتن<br>";
    }
}

echo "<h2>4. بررسی فایل‌های ضروری</h2>";
$required_files = [
    'install.php',
    'index.php',
    'config/db.sample.php',
    'assets/style.css',
    'assets/install.css',
    'api/search_users.php'
];

foreach ($required_files as $file) {
    echo "- $file: ";
    if (file_exists($file)) {
        echo "✅ موجود<br>";
    } else {
        echo "❌ موجود نیست<br>";
    }
}

echo "<h2>5. وضعیت نصب</h2>";
if (file_exists('installed.lock')) {
    echo "✅ سیستم نصب شده است<br>";
    echo "تاریخ نصب: " . file_get_contents('installed.lock');
} else {
    echo "⚠️ سیستم هنوز نصب نشده است<br>";
    echo '<a href="install.php">برای نصب کلیک کنید</a>';
}

echo "<h2>6. تست اتصال به دیتابیس</h2>";
if (file_exists('config/db.php')) {
    try {
        require_once 'config/db.php';
        echo "✅ اتصال به دیتابیس موفق<br>";
        
        // تست جداول
        $tables = ['users', 'messages', 'webrtc_signals', 'chat_files', 'user_blocks', 'recent_chats'];
        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "- جدول $table: ✅ موجود<br>";
                
                // تست تعداد رکوردها
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
                $count = $stmt->fetch()['count'];
                echo "&nbsp;&nbsp;تعداد رکوردها: $count<br>";
            } else {
                echo "- جدول $table: ❌ موجود نیست<br>";
            }
        }
        
        // تست trigger ها
        echo "<br><strong>تست Trigger ها:</strong><br>";
        $stmt = $pdo->query("SHOW TRIGGERS LIKE 'update_recent_chats_after_message'");
        if ($stmt->rowCount() > 0) {
            echo "- Trigger recent_chats: ✅ موجود<br>";
        } else {
            echo "- Trigger recent_chats: ❌ موجود نیست<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ خطا در اتصال: " . $e->getMessage();
    }
} else {
    echo "⚠️ فایل تنظیمات دیتابیس موجود نیست";
}

echo "<h2>7. تست پوشه‌های آپلود</h2>";
$upload_dirs = ['uploads', 'uploads/chat_files'];
foreach ($upload_dirs as $dir) {
    echo "- $dir: ";
    if (is_dir($dir)) {
        if (is_writable($dir)) {
            echo "✅ موجود و قابل نوشتن<br>";
        } else {
            echo "⚠️ موجود اما غیرقابل نوشتن<br>";
        }
    } else {
        echo "❌ موجود نیست<br>";
    }
}

echo "<h2>8. تست API ها</h2>";
$api_files = [
    'api/search_users.php',
    'api/get_public_users.php', 
    'api/get_recent_chats.php',
    'api/send_message.php',
    'api/get_messages.php',
    'api/upload_file.php',
    'api/delete_messages.php',
    'api/block_user.php'
];

foreach ($api_files as $file) {
    echo "- $file: ";
    if (file_exists($file)) {
        echo "✅ موجود<br>";
    } else {
        echo "❌ موجود نیست<br>";
    }
}

echo "<hr>";
echo "<p><strong>نکته:</strong> این فایل فقط برای تست است. پس از اطمینان از عملکرد سیستم، آن را حذف کنید.</p>";
echo '<p><a href="index.php">رفتن به صفحه اصلی</a></p>';
?>