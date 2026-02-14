<?php
/**
 * اسکریپت اصلاح چک نصب در تمام فایل‌های API
 */

echo "<h2>🔧 اصلاح چک نصب در فایل‌های API</h2>";

$api_files = glob('api/*.php');
$updated_files = 0;

foreach ($api_files as $file) {
    $content = file_get_contents($file);
    
    // جایگزینی چک نصب قدیمی با جدید
    $old_pattern = "if (!file_exists('../installed.lock')) {";
    $new_pattern = "if (!file_exists('../installed.lock') || !file_exists('../config/db.php')) {";
    
    if (strpos($content, $old_pattern) !== false) {
        $new_content = str_replace($old_pattern, $new_pattern, $content);
        file_put_contents($file, $new_content);
        echo "✅ بروزرسانی شد: " . basename($file) . "<br>";
        $updated_files++;
    }
}

echo "<br><strong>تعداد فایل‌های بروزرسانی شده: $updated_files</strong><br>";
echo "<p>✅ تمام فایل‌های API بروزرسانی شدند!</p>";
echo "<p><a href='install.php'>رفتن به صفحه نصب</a></p>";
?>