<?php
/**
 * خروج از حساب کاربری
 */

session_start();

// پاک کردن session
require_once 'config/db.php';
require_once 'includes/auth.php';

clearRememberMe($pdo);

session_destroy();

// حذف کوکی‌های session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// انتقال به صفحه ورود
header('Location: login.php');
exit;
?>