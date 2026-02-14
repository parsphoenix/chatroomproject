<?php
/**
 * صفحه چت گروهی
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
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$group_id = intval($_GET['group'] ?? 0);
if ($group_id <= 0) {
    header('Location: groups.php');
    exit;
}

require_once 'config/db.php';

// چک کردن عضویت در گروه
try {
    $stmt = $pdo->prepare("
        SELECT 
            g.id,
            g.name,
            g.description,
            u.username as creator_name,
            gm.status
        FROM groups_table g
        INNER JOIN users u ON g.creator_id = u.id
        LEFT JOIN group_members gm ON g.id = gm.group_id AND gm.user_id = ?
        WHERE g.id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $group_id]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group || $group['status'] !== 'accepted') {
        header('Location: groups.php');
        exit;
    }
    
    // آپدیت آخرین فعالیت کاربر جاری
    $stmt = $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    
    // دریافت تم کاربر
    $user_theme = 'light';
    try {
        $stmt = $pdo->prepare("SELECT theme_preference FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user_data) {
            $user_theme = $user_data['theme_preference'] ?: 'light';
        }
    } catch (PDOException $e) {
        // خطا - ادامه بده
    }
    
} catch (PDOException $e) {
    die(__('error_load_group') . ': ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html dir="<?= get_direction() ?>" lang="<?= get_lang_code() ?>" data-theme="<?= $user_theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('group_title', ['name' => htmlspecialchars($group['name'])]) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="chat-container">
        <div class="chat-header">
            <div class="chat-user-info">
                <div class="group-avatar"><?= strtoupper(substr($group['name'], 0, 1)) ?></div>
                <div>
                    <h2><?= htmlspecialchars($group['name']) ?></h2>
                    <div class="group-info">
                        <span id="memberCount"><?= __('loading') ?></span>
                        <span>• <?= __('created_by', ['name' => htmlspecialchars($group['creator_name'])]) ?></span>
                    </div>
                </div>
            </div>
            <div class="chat-actions">
                <button class="action-btn" id="themeToggle" title="<?= __('toggle_theme') ?>"><?= $user_theme === 'dark' ? '☀️' : '🌙' ?></button>
                <button class="action-btn" onclick="toggleSelectMode()" id="selectModeBtn"><?= __('select_messages') ?></button>
                <button class="action-btn" onclick="startGroupVideoCall()" id="videoCallBtn"><?= __('group_call') ?></button>
                <button class="action-btn" onclick="showGroupMembers()"><?= __('members') ?></button>
                <a href="groups.php" class="action-btn"><?= __('back') ?></a>
            </div>
        </div>
        
        <div class="chat-messages" id="chatMessages">
            <div class="alert info"><?= __('loading_messages') ?></div>
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
        <button class="bulk-action-btn" onclick="deleteSelectedMessages('for_all')">حذف برای همه</button>
        <button class="bulk-action-btn" onclick="cancelSelection()" style="background: #718096;">لغو</button>
    </div>
    
    <!-- Group Members Modal -->
    <div id="groupMembersModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>اعضای گروه</h3>
                <button class="close-btn" onclick="hideGroupMembers()">×</button>
            </div>
            <div class="modal-body">
                <div id="groupMembersList" class="members-list">
                    <div class="loading">در حال بارگذاری...</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Group Video Call Modal -->
    <div id="groupVideoCallModal" style="display: none;">
        <div class="group-call">
            <div class="group-call-header">
                <h3 id="callTitle">تماس گروهی: <?= htmlspecialchars($group['name']) ?></h3>
                <div id="callStatus" class="call-status">در حال برقراری تماس...</div>
            </div>
            
            <div class="group-video-container" id="groupVideoContainer">
                <!-- ویدیوها به صورت داینامیک اینجا اضافه می‌شوند -->
            </div>
            
            <div class="group-call-footer">
                <button class="call-control-btn" onclick="toggleMute()" id="groupMuteBtn">🎤</button>
                <button class="call-control-btn" onclick="toggleVideo()" id="groupVideoBtn">📹</button>
                <button class="call-control-btn danger" onclick="endGroupCall()">❌</button>
            </div>
            
            <div id="deviceStatus" class="device-status"></div>
        </div>
    </div>
    
    <!-- Notification Container -->
    <div id="notificationContainer"></div>
    
    <script src="assets/chat.js"></script>
    <script src="assets/group_chat.js"></script>
    <?php include 'includes/webrtc_loader.php'; ?>
    <script>
        // مقداردهی اولیه چت گروهی
        document.addEventListener('DOMContentLoaded', function() {
            // اولیه سازی تم
            const savedTheme = document.documentElement.getAttribute('data-theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
            localStorage.setItem('theme', savedTheme);

            // مدیریت تغییر تم
            const themeToggle = document.getElementById('themeToggle');
            if (themeToggle) {
                themeToggle.addEventListener('click', () => {
                    const currentTheme = document.documentElement.getAttribute('data-theme');
                    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                    
                    document.documentElement.setAttribute('data-theme', newTheme);
                    localStorage.setItem('theme', newTheme);
                    themeToggle.innerText = newTheme === 'dark' ? '☀️' : '🌙';
                    
                    // آپدیت در دیتابیس
                    updateThemePreference(newTheme);
                });
            }

            const groupId = <?= $group_id ?>;
            const currentUserId = <?= $_SESSION['user_id'] ?>;
            const groupName = '<?= htmlspecialchars($group['name']) ?>';
            
            // راه‌اندازی چت گروهی
            initGroupChat(groupId, currentUserId, groupName);
            
            // بارگذاری تعداد اعضا
            loadMemberCount();
            
            // درخواست مجوز notification
            requestNotificationPermission();
        });
        
        function updateThemePreference(theme) {
            fetch('api/update_settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `type=theme&value=${theme}`
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.error('Error updating theme preference:', data.message);
                }
            })
            .catch(error => {
                console.error('Error updating theme preference:', error);
            });
        }

        function loadMemberCount() {
            fetch('api/get_group_members.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'group_id=<?= $group_id ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('memberCount').textContent = data.count + ' عضو';
                }
            })
            .catch(error => {
                console.error('خطا در بارگذاری تعداد اعضا:', error);
            });
        }
    </script>
</body>
</html>