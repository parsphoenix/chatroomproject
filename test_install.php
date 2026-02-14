<?php
/**
 * تست وضعیت نصب
 */

echo "<h2>🔍 تست وضعیت نصب</h2>";

echo "<h3>فایل‌های مورد نیاز:</h3>";
$required_files = [
    'installed.lock' => 'فایل قفل نصب',
    'config/db.php' => 'فایل تنظیمات دیتابیس',
    'install.php' => 'فایل نصب',
    'index.php' => 'فایل اصلی',
    'login.php' => 'فایل ورود',
    'assets/install.css' => 'فایل CSS نصب'
];

foreach ($required_files as $file => $description) {
    if (file_exists($file)) {
        echo "✅ $description ($file) - موجود<br>";
    } else {
        echo "❌ $description ($file) - موجود نیست<br>";
    }
}

echo "<h3>وضعیت نصب:</h3>";
if (file_exists('installed.lock') && file_exists('config/db.php')) {
    echo "✅ سیستم نصب شده است<br>";
    echo "📅 تاریخ نصب: " . file_get_contents('installed.lock') . "<br>";
    echo "<p><a href='index.php'>رفتن به سایت</a></p>";
} else {
    echo "❌ سیستم نصب نشده است<br>";
    echo "<p><a href='install.php'>شروع نصب</a></p>";
}

echo "<hr>";
echo "<h3>تست مسیرها:</h3>";
echo "📁 مسیر فعلی: " . __DIR__ . "<br>";
echo "🌐 URL فعلی: " . (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] : 'CLI Mode') . "<br>";

if (isset($_SERVER['HTTP_HOST'])) {
    $base_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
    echo "<p><strong>لینک‌های مفید:</strong></p>";
    echo "<ul>";
    echo "<li><a href='$base_url/install.php'>صفحه نصب</a></li>";
    echo "<li><a href='$base_url/index.php'>صفحه اصلی</a></li>";
    echo "<li><a href='$base_url/login.php'>صفحه ورود</a></li>";
    echo "</ul>";
}
?>