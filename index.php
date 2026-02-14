<?php
/**
 * صفحه اصلی - چک نصب و مدیریت ورود
 */

// چک کردن نصب
if (!file_exists('installed.lock') || !file_exists('config/db.php')) {
    header('Location: install.php');
    exit;
}

session_start();

// اگر کاربر لاگین است به dashboard برود
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// اگر کاربر لاگین نیست به صفحه ورود برود
header('Location: login.php');
exit;
?>