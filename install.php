<?php
/**
 * صفحه نصب خودکار وب‌چت
 * این فایل اولین بار که سایت باز می‌شود اجرا می‌شود
 */

require_once 'includes/lang_helper.php';

// چک کردن اینکه قبلاً نصب نشده باشد
if (file_exists('installed.lock')) {
    // اگر کاربر مستقیماً install.php را باز کرده، اجازه نصب مجدد بده
    if (!isset($_GET['force']) && !isset($_POST['db_host'])) {
        ?>
        <!DOCTYPE html>
        <html dir="<?= get_direction() ?>" lang="<?= get_lang_code() ?>">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?= __('already_installed') ?></title>
            <link rel="stylesheet" href="assets/fonts.css">
            <style>
                body { font-family: 'Vazir', 'Tahoma', sans-serif !important; background: #f5f5f5; padding: 50px; text-align: center; }
                * { font-family: 'Vazir', 'Tahoma', sans-serif !important; }
                .container { background: white; padding: 40px; border-radius: 10px; max-width: 500px; margin: 0 auto; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
                .btn { padding: 10px 20px; margin: 10px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
                .btn-primary { background: #007bff; color: white; }
                .btn-danger { background: #dc3545; color: white; }
                .btn-success { background: #28a745; color: white; }
                .lang-selector { margin-bottom: 20px; }
                .lang-link { margin: 0 10px; text-decoration: none; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class="lang-selector">
                <a href="?lang=fa" class="lang-link" style="color: <?= get_lang_code() == 'fa' ? '#007bff' : '#666' ?>;">فارسی</a>
                |
                <a href="?lang=en" class="lang-link" style="color: <?= get_lang_code() == 'en' ? '#007bff' : '#666' ?>;">English</a>
            </div>
            <div class="container">
                <h2>🎉 <?= __('already_installed') ?></h2>
                <p><?= __('ready_to_use') ?></p>
                <div>
                    <a href="index.php" class="btn btn-success">🏠 <?= __('enter_site') ?></a>
                    <a href="?force=1" class="btn btn-danger">🔄 <?= __('reinstall') ?></a>
                    <a href="dashboard.php" class="btn btn-primary">📊 <?= __('dashboard') ?></a>
                </div>
                <hr style="margin: 30px 0;">
                <h3>📋 <?= __('useful_info') ?></h3>
                <p><strong><?= __('important_files') ?>:</strong></p>
                <ul style="text-align: <?= get_direction() == 'rtl' ? 'right' : 'left' ?>; display: inline-block;">
                    <li>config/db.php - <?= __('db_config') ?></li>
                    <li>installed.lock - <?= __('install_lock') ?></li>
                    <li>admin_panel.php - <?= __('admin_panel') ?></li>
                </ul>
                <p><?= __('reinstall_note') ?></p>
            </div>
        </body>
        </html>
        <?php
        exit;
    } else {
        // حذف فایل قفل برای نصب مجدد
        if (file_exists('installed.lock')) {
            unlink('installed.lock');
        }
    }
}

$error = '';
$success = '';

// توابع تشخیص خودکار تنظیمات هاست
function getDefaultDbHost() {
    // تشخیص هاست‌های معروف
    $server_name = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // هاست‌های معروف ایرانی
    if (strpos($server_name, 'parspack.com') !== false) return 'localhost';
    if (strpos($server_name, 'hostiran.com') !== false) return 'localhost';
    if (strpos($server_name, 'asiatech.com') !== false) return 'localhost';
    if (strpos($server_name, 'fanavaran.com') !== false) return 'localhost';
    if (strpos($server_name, 'iranserver.com') !== false) return 'localhost';
    
    // هاست‌های خارجی
    if (strpos($server_name, 'cpanel') !== false) return 'localhost';
    if (strpos($server_name, 'hostgator') !== false) return 'localhost';
    if (strpos($server_name, 'godaddy') !== false) return 'localhost';
    if (strpos($server_name, '000webhost') !== false) return 'localhost';
    
    return 'localhost';
}

function getDefaultDbPort() {
    // بیشتر هاست‌ها از پورت استاندارد MySQL استفاده می‌کنند
    return '3306';
}

function getHostInfo() {
    $info = [];
    $info['server_name'] = $_SERVER['SERVER_NAME'] ?? 'نامشخص';
    $info['server_software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'نامشخص';
    $info['php_version'] = PHP_VERSION;
    $info['mysql_available'] = extension_loaded('pdo_mysql') ? 'بله' : 'خیر';
    return $info;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // دریافت اطلاعات فرم
    $db_host = trim($_POST['db_host'] ?? 'localhost');
    $db_name = trim($_POST['db_name'] ?? '');
    $db_user = trim($_POST['db_user'] ?? '');
    $db_pass = $_POST['db_pass'] ?? '';
    $db_port = trim($_POST['db_port'] ?? '3306');
    $admin_username = trim($_POST['admin_username'] ?? '');
    $admin_password = $_POST['admin_password'] ?? '';
    
    // اعتبارسنجی ساده
    if (empty($db_name) || empty($db_user) || empty($admin_username) || empty($admin_password)) {
        $error = __('error_all_fields');
    } elseif (strlen($admin_username) < 3 || strlen($admin_username) > 50) {
        $error = __('error_username_length');
    } elseif (strlen($admin_password) < 6) {
        $error = __('error_password_length');
    } else {
        try {
            // تست اتصال به دیتابیس
            $dsn = "mysql:host=$db_host;port=$db_port;charset=utf8mb4";
            $pdo = new PDO($dsn, $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // ساخت دیتابیس اگر وجود نداشت
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$db_name`");
            
            // ساخت جدول کاربران
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    username VARCHAR(50) UNIQUE NOT NULL,
                    password_hash VARCHAR(255) NOT NULL,
                    profile_picture VARCHAR(255) DEFAULT NULL,
                    theme_preference VARCHAR(20) DEFAULT 'light',
                    language_preference VARCHAR(5) DEFAULT 'fa',
                    preferred_bitrate INT DEFAULT 500,
                    show_online_status BOOLEAN DEFAULT TRUE,
                    enable_notifications BOOLEAN DEFAULT TRUE,
                    user_role ENUM('user', 'admin') DEFAULT 'user',
                    is_public BOOLEAN DEFAULT FALSE,
                    last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_username (username),
                    INDEX idx_last_seen (last_seen),
                    INDEX idx_public (is_public)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            // ساخت جدول پیام‌ها
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS messages (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    sender_id INT NOT NULL,
                    receiver_id INT NOT NULL,
                    message TEXT NOT NULL,
                    is_read BOOLEAN DEFAULT FALSE,
                    status ENUM('sent', 'delivered', 'read') DEFAULT 'sent',
                    type VARCHAR(20) DEFAULT 'text',
                    metadata TEXT DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
                    INDEX idx_sender_receiver (sender_id, receiver_id),
                    INDEX idx_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            // ساخت جدول signaling برای WebRTC
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS webrtc_signals (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    from_user_id INT NOT NULL,
                    to_user_id INT NOT NULL,
                    signal_type ENUM('offer', 'answer', 'ice') NOT NULL,
                    signal_data TEXT NOT NULL,
                    is_read BOOLEAN DEFAULT FALSE,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE,
                    INDEX idx_to_user (to_user_id, is_read)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            // ساخت جدول فایل‌ها
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS chat_files (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    sender_id INT NOT NULL,
                    receiver_id INT NOT NULL,
                    original_name VARCHAR(255) NOT NULL,
                    file_name VARCHAR(255) NOT NULL,
                    file_size INT NOT NULL,
                    file_type VARCHAR(100) NOT NULL,
                    file_path VARCHAR(500) NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
                    INDEX idx_sender_receiver (sender_id, receiver_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            // ساخت جدول بلاک کردن کاربران
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS user_blocks (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    blocker_id INT NOT NULL,
                    blocked_id INT NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE,
                    UNIQUE KEY unique_block (blocker_id, blocked_id),
                    INDEX idx_blocker (blocker_id),
                    INDEX idx_blocked (blocked_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            // ساخت جدول چت‌های اخیر
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS recent_chats (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    chat_with_id INT NOT NULL,
                    last_message_id INT,
                    unread_count INT DEFAULT 0,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (chat_with_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (last_message_id) REFERENCES messages(id) ON DELETE SET NULL,
                    UNIQUE KEY unique_chat (user_id, chat_with_id),
                    INDEX idx_user_updated (user_id, updated_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            // ساخت جدول گروه‌ها
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS groups_table (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    name VARCHAR(100) NOT NULL,
                    description TEXT,
                    creator_id INT NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
                    INDEX idx_creator (creator_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            // ساخت جدول اعضای گروه
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS group_members (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    group_id INT NOT NULL,
                    user_id INT NOT NULL,
                    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
                    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (group_id) REFERENCES groups_table(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    UNIQUE KEY unique_member (group_id, user_id),
                    INDEX idx_group_status (group_id, status),
                    INDEX idx_user_status (user_id, status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            // ساخت جدول پیام‌های گروهی
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS group_messages (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    group_id INT NOT NULL,
                    sender_id INT NOT NULL,
                    message TEXT NOT NULL,
                    type ENUM('text', 'file', 'system') DEFAULT 'text',
                    file_path VARCHAR(255) DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (group_id) REFERENCES groups_table(id) ON DELETE CASCADE,
                    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
                    INDEX idx_group_sender (group_id, sender_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // ساخت جدول تماس‌های گروهی
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS group_calls (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    group_id INT NOT NULL,
                    initiator_id INT NOT NULL,
                    status ENUM('active', 'ended') DEFAULT 'active',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    ended_at DATETIME DEFAULT NULL,
                    FOREIGN KEY (group_id) REFERENCES groups_table(id) ON DELETE CASCADE,
                    FOREIGN KEY (initiator_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // ساخت جدول شرکت‌کنندگان در تماس گروهی
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS group_call_participants (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    call_id INT NOT NULL,
                    user_id INT NOT NULL,
                    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    last_ping DATETIME DEFAULT CURRENT_TIMESTAMP,
                    is_active BOOLEAN DEFAULT TRUE,
                    FOREIGN KEY (call_id) REFERENCES group_calls(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    UNIQUE KEY idx_call_user (call_id, user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            // ساخت جدول فایل‌های گروهی
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS group_files (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    group_id INT NOT NULL,
                    sender_id INT NOT NULL,
                    original_name VARCHAR(255) NOT NULL,
                    file_name VARCHAR(255) NOT NULL,
                    file_size INT NOT NULL,
                    file_type VARCHAR(100) NOT NULL,
                    file_path VARCHAR(500) NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (group_id) REFERENCES groups_table(id) ON DELETE CASCADE,
                    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
                    INDEX idx_group_created (group_id, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            // نکته: TRIGGER ها در هاست‌های مشترک معمولاً مجاز نیستند
            // بنابراین recent_chats را به صورت دستی در API ها مدیریت می‌کنیم
            
            // ساخت جداول سیستم ادمین
            try {
                // جدول ممنوعیت کاربران
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS user_bans (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        banned_user_id INT NOT NULL,
                        banned_by_admin_id INT NOT NULL,
                        ban_reason TEXT,
                        banned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        is_active BOOLEAN DEFAULT TRUE,
                        browser_fingerprint TEXT,
                        ip_address VARCHAR(45),
                        FOREIGN KEY (banned_user_id) REFERENCES users(id) ON DELETE CASCADE,
                        FOREIGN KEY (banned_by_admin_id) REFERENCES users(id) ON DELETE CASCADE,
                        INDEX idx_banned_user (banned_user_id),
                        INDEX idx_active_bans (is_active)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
                
                // اضافه کردن فیلد نقش کاربر
                $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS user_role ENUM('user', 'admin') DEFAULT 'user'");
                
                // جدول لاگ فعالیت‌های ادمین
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS admin_logs (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        admin_id INT NOT NULL,
                        action_type ENUM('ban_user', 'unban_user', 'view_users') NOT NULL,
                        target_user_id INT,
                        action_details TEXT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
                        INDEX idx_admin_logs (admin_id, created_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            } catch (PDOException $e) {
                // اگر خطا در ساخت جداول ادمین بود، ادامه بده
                error_log('خطا در ساخت جداول ادمین: ' . $e->getMessage());
            }
            
            // ساخت حساب ادمین اولیه
            $password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, user_role) VALUES (?, ?, 'admin')");
            $stmt->execute([$admin_username, $password_hash]);
            
            // ساخت پوشه‌های مورد نیاز
            if (!is_dir('config')) {
                mkdir('config', 0755, true);
            }
            if (!is_dir('uploads')) {
                mkdir('uploads', 0755, true);
            }
            if (!is_dir('uploads/chat_files')) {
                mkdir('uploads/chat_files', 0755, true);
            }
            
            // ساخت فایل config/db.php
            $config_content = "<?php\n";
            $config_content .= "// تنظیمات اتصال به دیتابیس - توسط installer ساخته شده\n";
            $config_content .= "define('DB_HOST', '" . addslashes($db_host) . "');\n";
            $config_content .= "define('DB_NAME', '" . addslashes($db_name) . "');\n";
            $config_content .= "define('DB_USER', '" . addslashes($db_user) . "');\n";
            $config_content .= "define('DB_PASS', '" . addslashes($db_pass) . "');\n";
            $config_content .= "define('DB_PORT', '" . addslashes($db_port) . "');\n\n";
            $config_content .= "// ساخت اتصال PDO\n";
            $config_content .= "try {\n";
            $config_content .= "    \$dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';\n";
            $config_content .= "    \$pdo = new PDO(\$dsn, DB_USER, DB_PASS);\n";
            $config_content .= "    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n";
            $config_content .= "} catch (PDOException \$e) {\n";
            $config_content .= "    die('خطا در اتصال به دیتابیس: ' . \$e->getMessage());\n";
            $config_content .= "}\n";
            
            // ذخیره زبان در دیتابیس یا فایل تنظیمات (فعلاً در فایل تنظیمات)
            $config_content .= "define('APP_LANG', '" . addslashes(get_lang_code()) . "');\n";
            $config_content .= "?>";
            file_put_contents('config/db.php', $config_content);
            
            // ساخت فایل قفل نصب
            file_put_contents('installed.lock', date('Y-m-d H:i:s'));
            
            $success = __('success_install');
            header("refresh:3;url=login.php");
        } catch (PDOException $e) {
            $error_code = $e->getCode();
            $error_message = $e->getMessage();
            
            if ($error_code == 1045) {
                $error = __('error_db_access');
            } elseif ($error_code == 1049) {
                $error = __('error_db_not_found');
            } else {
                $error = __('error_db') . ': ' . $error_message;
            }
        } catch (Exception $e) {
            $error = __('error_unexpected') . ': ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html dir="<?= get_direction() ?>" lang="<?= get_lang_code() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('install_title') ?></title>
    <link rel="stylesheet" href="assets/install.css">
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
<body>
    <div class="lang-selector-top">
        <a href="?lang=fa<?= isset($_GET['force']) ? '&force=1' : '' ?>" class="lang-link" style="color: <?= get_lang_code() == 'fa' ? '#4facfe' : '#666' ?>;">فارسی</a>
        |
        <a href="?lang=en<?= isset($_GET['force']) ? '&force=1' : '' ?>" class="lang-link" style="color: <?= get_lang_code() == 'en' ? '#4facfe' : '#666' ?>;">English</a>
    </div>
    <div class="install-container">
        <div class="install-header">
            <h1><?= __('install_button') ?></h1>
            <p><?= __('install_title') ?></p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert error">
                <strong><?= __('error') ?>!</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert success">
                <strong><?= __('success') ?>!</strong> <?= htmlspecialchars($success) ?>
                <div class="loading-spinner"></div>
            </div>
        <?php else: ?>
            
            <!-- اطلاعات سرور -->
            <div class="server-info">
                <h4>📊 <?= __('server_info') ?></h4>
                <?php $host_info = getHostInfo(); ?>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label"><?= __('server_name') ?>:</span>
                        <span class="info-value"><?= htmlspecialchars($host_info['server_name']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><?= __('server_software') ?>:</span>
                        <span class="info-value"><?= htmlspecialchars($host_info['server_software']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><?= __('php_version') ?>:</span>
                        <span class="info-value"><?= htmlspecialchars($host_info['php_version']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><?= __('mysql_support') ?>:</span>
                        <span class="info-value <?= $host_info['mysql_available'] === 'بله' ? 'text-success' : 'text-error' ?>">
                            <?= $host_info['mysql_available'] === 'بله' ? __('yes') : __('no') ?>
                        </span>
                    </div>
                </div>
            </div>
            <!-- راهنمای سریع -->
            <div class="quick-guide">
                <h4>📋 <?= __('quick_guide') ?></h4>
                <div class="guide-steps">
                    <div class="guide-step">
                        <span class="step-number">1</span>
                        <span class="step-text"><?= __('step_1') ?></span>
                    </div>
                    <div class="guide-step">
                        <span class="step-number">2</span>
                        <span class="step-text"><?= __('step_2') ?></span>
                    </div>
                    <div class="guide-step">
                        <span class="step-number">3</span>
                        <span class="step-text"><?= __('step_3') ?></span>
                    </div>
                    <div class="guide-step">
                        <span class="step-number">4</span>
                        <span class="step-text"><?= __('step_4') ?></span>
                    </div>
                </div>
            </div>
            
            <form method="POST" class="install-form">
                <div class="form-section">
                    <h3>📊 <?= __('db_info') ?></h3>
                    <div class="form-group">
                        <label for="db_host"><?= __('db_host') ?>:</label>
                        <input type="text" id="db_host" name="db_host" value="<?= getDefaultDbHost() ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="db_name"><?= __('db_name') ?>:</label>
                        <input type="text" id="db_name" name="db_name" placeholder="webchat_db" required>
                    </div>
                    <div class="form-group">
                        <label for="db_user"><?= __('db_user') ?>:</label>
                        <input type="text" id="db_user" name="db_user" placeholder="root" required>
                    </div>
                    <div class="form-group">
                        <label for="db_pass"><?= __('db_pass') ?>:</label>
                        <input type="password" id="db_pass" name="db_pass">
                    </div>
                    <div class="form-group">
                        <label for="db_port"><?= __('db_port') ?>:</label>
                        <input type="text" id="db_port" name="db_port" value="<?= getDefaultDbPort() ?>" required>
                    </div>
                </div>

                <div class="form-section">
                    <h3>👤 <?= __('admin_info') ?></h3>
                    <div class="form-group">
                        <label for="admin_username"><?= __('admin_username') ?>:</label>
                        <input type="text" id="admin_username" name="admin_username" placeholder="admin" required>
                    </div>
                    <div class="form-group">
                        <label for="admin_password"><?= __('admin_password') ?>:</label>
                        <input type="password" id="admin_password" name="admin_password" required>
                    </div>
                </div>

                <button type="submit" class="install-btn">
                    <span><?= __('install_button') ?></span>
                    <div class="btn-loading" style="display: none;"></div>
                </button>
            </form>
        <?php endif; ?>
        
        <div class="install-footer">
            <p><?= __('install_footer') ?></p>
        </div>
    </div>
    
    <script>
        // نمایش loading هنگام submit
        document.querySelector('.install-form')?.addEventListener('submit', function() {
            const btn = document.querySelector('.install-btn');
            const span = btn.querySelector('span');
            const loading = btn.querySelector('.btn-loading');
            
            span.style.display = 'none';
            loading.style.display = 'inline-block';
            btn.disabled = true;
        });
    </script>
</body>
</html>