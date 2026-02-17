<?php
/**
 * صفحه ورود کاربران
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
require_once 'config/db.php';
require_once 'includes/auth.php';

if (checkAuth($pdo)) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$captcha_question = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'config/db.php';
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $captcha_input = trim($_POST['captcha_answer'] ?? '');
    $expected_captcha = $_SESSION['captcha_answer'] ?? null;
    
    if ($expected_captcha === null || $captcha_input === '' || intval($captcha_input) !== intval($expected_captcha)) {
        $error = __('error_captcha');
    } elseif (empty($username) || empty($password)) {
        $error = __('error_login_fields');
    } else {
        try {
            // جستجوی کاربر
            $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // ورود موفق
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                
                // مدیریت "مرا به خاطر بسپار"
                if (isset($_POST['remember_me'])) {
                    setRememberMe($pdo, $user['id']);
                }
                
                // آپدیت آخرین فعالیت
                $stmt = $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                header('Location: dashboard.php');
                exit;
            } else {
                $error = __('error_login_invalid');
            }
        } catch (PDOException $e) {
            $error = __('error_login') . ': ' . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !empty($error)) {
    $a = random_int(1, 9);
    $b = random_int(1, 9);
    $_SESSION['captcha_answer'] = $a + $b;
    $_SESSION['captcha_question'] = $a . ' + ' . $b;
}

$captcha_question = $_SESSION['captcha_question'] ?? '';
?>
<!DOCTYPE html>
<html dir="<?= get_direction() ?>" lang="<?= get_lang_code() ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('login_title') ?></title>
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
                <h1>🔐 <?= __('login') ?></h1>
                <p><?= __('welcome_back') ?></p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label for="username"><?= __('username') ?>:</label>
                    <input type="text" id="username" name="username" 
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
                           placeholder="<?= __('enter_username') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="password"><?= __('password') ?>:</label>
                    <input type="password" id="password" name="password" 
                           placeholder="<?= __('enter_password') ?>" required>
                </div>

                <div class="form-group">
                    <label for="captcha_answer"><?= __('captcha_question') ?>: <?= htmlspecialchars($captcha_question) ?></label>
                    <input type="text" id="captcha_answer" name="captcha_answer"
                           placeholder="<?= __('captcha_placeholder') ?>" required>
                </div>
                
                <div class="form-group checkbox-group">
                    <label class="checkbox-container">
                        <input type="checkbox" name="remember_me" id="remember_me">
                        <span class="checkmark"></span>
                        <?= __('remember_me') ?: 'مرا به خاطر بسپار' ?>
                    </label>
                </div>
                
                <button type="submit" class="auth-btn"><?= __('login') ?></button>
            </form>
            
            <div class="auth-footer">
                <p><?= __('no_account') ?> <a href="register.php"><?= __('register_now') ?></a></p>
            </div>
        </div>
    </div>
    <?php include 'includes/webrtc_loader.php'; ?>
</body>
</html>
