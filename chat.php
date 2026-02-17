<?php
/**
 * صفحه چت با کاربر مشخص
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

// چک لاگین
require_once 'config/db.php';
require_once 'includes/auth.php';

if (!checkAuth($pdo)) {
    header('Location: login.php');
    exit;
}

$target_username = $_GET['user'] ?? '';
if (empty($target_username)) {
    header('Location: dashboard.php');
    exit;
}

require_once 'config/db.php';
require_once 'check_ban_middleware.php';

// چک ممنوعیت کاربر
checkUserBan($pdo, $_SESSION['user_id']);

// پیدا کردن کاربر مقصد
try {
    $stmt = $pdo->prepare("
        SELECT 
            id, 
            username, 
            last_seen,
            CASE 
                WHEN last_seen >= DATE_SUB(NOW(), INTERVAL 2 MINUTE) THEN 1 
                ELSE 0 
            END as is_online
        FROM users 
        WHERE username = ? AND id != ?
    ");
    $stmt->execute([$target_username, $_SESSION['user_id']]);
    $target_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$target_user) {
        header('Location: dashboard.php');
        exit;
    }
    
    // آپدیت آخرین فعالیت کاربر جاری
    $stmt = $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    
    // دریافت تم کاربر
    $stmt = $pdo->prepare("SELECT theme_preference FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_theme = ($user_data && $user_data['theme_preference']) ? $user_data['theme_preference'] : 'light';
    
} catch (PDOException $e) {
    die(__('error_load_user') . ': ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html dir="<?= get_direction() ?>" lang="<?= get_lang_code() ?>" data-theme="<?= $user_theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('chat_with', ['username' => htmlspecialchars($target_user['username'])]) ?> - <?= __('app_name') ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/chat-fixes.css">
</head>
<body>
    <div class="chat-container">
        <div class="chat-header">
            <div class="chat-user-info">
                <div class="user-avatar"><?= strtoupper(substr($target_user['username'], 0, 1)) ?></div>
                <div>
                    <h2><?= htmlspecialchars($target_user['username']) ?></h2>
                    <div class="user-status <?= $target_user['is_online'] ? 'status-online' : 'status-offline' ?>" id="userStatus">
                        <span class="<?= $target_user['is_online'] ? 'online' : 'offline' ?>-indicator"></span>
                        <span id="statusText"><?= $target_user['is_online'] ? __('online') : __('offline') ?></span>
                    </div>
                </div>
            </div>
            <div class="chat-actions">
                <button class="action-btn" onclick="toggleSelectMode()" id="selectModeBtn"><?= __('select_messages') ?></button>
                <button class="action-btn" onclick="startVideoCall()" id="videoCallBtn"><?= __('video_call') ?></button>
                <button class="action-btn" onclick="startAudioCall()" id="audioCallBtn"><?= __('audio_call') ?></button>
                <button class="action-btn" onclick="blockUser('<?= htmlspecialchars($target_user['username']) ?>')" id="blockBtn"><?= __('block') ?></button>
                <a href="dashboard.php" class="action-btn"><?= __('back') ?></a>
            </div>
        </div>
        
        <div class="chat-messages" id="chatMessages">
            <div class="alert info" id="loadingMessage"><?= __('loading_messages') ?></div>
        </div>
        
        <div class="chat-input">
            <div class="input-group">
                <div class="file-input-container">
                    <input type="file" id="fileInput" class="file-input" accept="image/*,.pdf,.doc,.docx,.txt,.zip,.rar">
                    <button class="file-btn" onclick="document.getElementById('fileInput').click()">📎</button>
                </div>
                <input type="text" id="messageInput" placeholder="<?= __('type_message') ?>" autocomplete="off">
                <button class="send-btn"><?= __('send') ?></button>
            </div>
        </div>
    </div>
    
    <!-- Bulk Actions -->
    <div class="bulk-actions" id="bulkActions">
        <span id="selectedCount">0 پیام انتخاب شده</span>
        <button class="bulk-action-btn" onclick="deleteSelectedMessages('for_me')">حذف برای من</button>
        <button class="bulk-action-btn" onclick="deleteSelectedMessages('for_both')">حذف برای هر دو</button>
        <button class="bulk-action-btn" onclick="cancelSelection()" style="background: #718096;">لغو</button>
    </div>
    
    <!-- Modals are now loaded via webrtc_loader.php -->
    
    <!-- Notification Container -->
    <div id="notificationContainer"></div>
    
    <script src="assets/chat.js"></script>
    <?php include 'includes/webrtc_loader.php'; ?>
    <script>
        // مقداردهی اولیه چت و WebRTC
        document.addEventListener('DOMContentLoaded', function() {
            // بارگذاری تم از LocalStorage یا صفت data-theme که توسط PHP تنظیم شده
            const savedTheme = localStorage.getItem('theme') || document.documentElement.getAttribute('data-theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
            localStorage.setItem('theme', savedTheme);

            const targetUserId = <?= $target_user['id'] ?>;
            const currentUserId = <?= $_SESSION['user_id'] ?>;
            const targetUsername = '<?= htmlspecialchars($target_user['username']) ?>';
            
            // دریافت تنظیمات اعلان از PHP (فرض شده قبلاً دریافت شده)
            <?php 
                $stmt = $pdo->prepare("SELECT enable_notifications FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $userSettings = $stmt->fetch(PDO::FETCH_ASSOC);
                $enableNotifications = $userSettings['enable_notifications'] ? 'true' : 'false';
            ?>
            const enableNotifications = <?= $enableNotifications ?>;

            // راه‌اندازی چت
            initChat(targetUserId, currentUserId, targetUsername, enableNotifications);
            
            // همیشه WebRTC جدید با targetUserId صحیح بساز
            // اگر قبلاً وجود داره، اول polling رو متوقف کن
            if (window.webrtcManager && window.webrtcManager.signalingInterval) {
                clearInterval(window.webrtcManager.signalingInterval);
            }
            initWebRTC(targetUserId, currentUserId);
            console.log('WebRTC initialized for chat with user:', targetUserId);
            
            // درخواست مجوز notification
            if (typeof requestNotificationPermission === 'function') {
                requestNotificationPermission();
            }
        });
    </script>
</body>
</html>