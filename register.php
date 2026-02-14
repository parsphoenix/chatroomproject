<?php
/**
 * صفحه ثبت‌نام کاربران جدید
 */

require_once 'includes/lang_helper.php';

// چک نصب
if (!file_exists('installed.lock') || !file_exists('config/db.php')) {
    header('Location: install.php');
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// اگر کاربر لاگین است به dashboard برود
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'config/db.php';
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // اعتبارسنجی
    if (empty($username) || empty($password) || empty($confirm_password)) {
        $error = __('error_all_fields');
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $error = __('error_username_length');
    } elseif (strlen($password) < 6) {
        $error = __('error_password_length');
    } elseif ($password !== $confirm_password) {
        $error = __('error_password_match');
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = __('error_username_format');
    } else {
        try {
            // چک کردن تکراری نبودن نام کاربری
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            
            if ($stmt->fetch()) {
                $error = __('error_username_exists');
            } else {
                // ثبت کاربر جدید
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
                $stmt->execute([$username, $password_hash]);
                
                $success = __('success_register');
                header("refresh:2;url=login.php");
            }
        } catch (PDOException $e) {
            $error = __('error_register') . ': ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html dir="<?= get_direction() ?>" lang="<?= get_lang_code() ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('register_title') ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <script>
        // اعمال تم ذخیره شده بلافاصله برای جلوگیری از پرش تصویر
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    <style>
        .lang-selector-top {
            position: absolute;
            top: 20px;
            left: 20px;
            background: white;
            padding: 10px 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        [dir="ltr"] .lang-selector-top {
            left: auto;
            right: 20px;
        }
        .lang-link {
            text-decoration: none;
            font-weight: bold;
            margin: 0 5px;
        }
    </style>
</head>
<body class="auth-container">
    <div class="lang-selector-top">
        <a href="?lang=fa" class="lang-link" style="color: <?= get_lang_code() == 'fa' ? '#4facfe' : '#666' ?>;">فارسی</a>
        |
        <a href="?lang=en" class="lang-link" style="color: <?= get_lang_code() == 'en' ? '#4facfe' : '#666' ?>;">English</a>
    </div>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>👤 <?= __('register') ?></h1>
                <p><?= __('create_account') ?></p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert success">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php else: ?>
                <form method="POST" class="auth-form">
                    <div class="form-group">
                        <label for="username"><?= __('username') ?>:</label>
                        <input type="text" id="username" name="username" 
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
                               placeholder="<?= __('enter_username') ?>" required>
                        <small><?= __('username_hint') ?></small>
                    </div>
                    
                    <div class="form-group">
                        <label for="password"><?= __('password') ?>:</label>
                        <input type="password" id="password" name="password" 
                               placeholder="<?= __('enter_password') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password"><?= __('confirm_password') ?>:</label>
                        <input type="password" id="confirm_password" name="confirm_password" 
                               placeholder="<?= __('enter_confirm_password') ?>" required>
                    </div>
                    
                    <button type="submit" class="auth-btn"><?= __('register') ?></button>
                </form>
            <?php endif; ?>
            
            <div class="auth-footer">
                <p><?= __('have_account') ?> <a href="login.php"><?= __('login_now') ?></a></p>
            </div>
        </div>
    </div>
    <?php include 'includes/webrtc_loader.php'; ?>
</body>
</html>