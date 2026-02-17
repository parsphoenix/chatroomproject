<?php
/**
 * صفحه تنظیمات کاربر
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

require_once 'config/db.php';
require_once 'check_ban_middleware.php';

// چک ممنوعیت کاربر
checkUserBan($pdo, $_SESSION['user_id']);

// دریافت اطلاعات کاربر
try {
    $stmt = $pdo->prepare("
        SELECT 
            username, 
            profile_picture,
            created_at,
            show_online_status,
            enable_notifications,
            theme_preference,
            language_preference,
            preferred_bitrate
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header('Location: logout.php');
        exit;
    }
} catch (PDOException $e) {
    die('خطا در بارگذاری اطلاعات کاربر: ' . $e->getMessage());
}

// آمار کاربر
try {
    // تعداد پیام‌های ارسالی
    $stmt = $pdo->prepare("SELECT COUNT(*) as sent_messages FROM messages WHERE sender_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $sent_messages = $stmt->fetch()['sent_messages'];
    
    // تعداد پیام‌های دریافتی
    $stmt = $pdo->prepare("SELECT COUNT(*) as received_messages FROM messages WHERE receiver_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $received_messages = $stmt->fetch()['received_messages'];
    
    // تعداد گروه‌های عضو
    $stmt = $pdo->prepare("SELECT COUNT(*) as joined_groups FROM group_members WHERE user_id = ? AND status = 'accepted'");
    $stmt->execute([$_SESSION['user_id']]);
    $joined_groups = $stmt->fetch()['joined_groups'];
    
    // تعداد گروه‌های ساخته شده
    $stmt = $pdo->prepare("SELECT COUNT(*) as created_groups FROM groups_table WHERE creator_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $created_groups = $stmt->fetch()['created_groups'];
    
} catch (PDOException $e) {
    $sent_messages = $received_messages = $joined_groups = $created_groups = 0;
}
?>
<!DOCTYPE html>
<html dir="<?= get_direction() ?>" lang="<?= get_lang_code() ?>" data-theme="<?= $user['theme_preference'] ?? 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تنظیمات - وب‌چت</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/chat-fixes.css">
    <link rel="stylesheet" href="assets/settings.css">
    <script>
        // اعمال تم ذخیره شده بلافاصله برای جلوگیری از پرش تصویر
        (function() {
            const savedTheme = localStorage.getItem('theme') || '<?= $user['theme_preference'] ?? 'light' ?>' || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
</head>
<body>
    <div class="settings-container">
        <!-- Header -->
        <div class="settings-header">
            <div class="header-content">
                <div class="header-left">
                    <h1>⚙️ تنظیمات حساب کاربری</h1>
                    <p>مدیریت حساب و تنظیمات شخصی</p>
                </div>
                <div class="header-right">
                    <a href="dashboard.php" class="back-btn">🔙 بازگشت</a>
                </div>
            </div>
        </div>
        
        <!-- User Info Card -->
        <div class="user-info-card">
            <div class="user-avatar-container">
                <div class="user-avatar-large" id="profileDisplay">
                    <?php if ($user['profile_picture']): ?>
                        <img src="uploads/profile_pics/<?= htmlspecialchars($user['profile_picture']) ?>" alt="Profile">
                    <?php else: ?>
                        <?= strtoupper(substr($user['username'], 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div class="avatar-edit-overlay" onclick="document.getElementById('profileInput').click()">
                    <span>📷</span>
                </div>
                <input type="file" id="profileInput" style="display: none;" accept="image/*" onchange="uploadProfilePicture(this)">
            </div>
            <div class="user-details">
                <h2><?= htmlspecialchars($user['username']) ?></h2>
                <p><?= __('member_since') ?>: <?= date('Y/m/d', strtotime($user['created_at'])) ?></p>
                <div class="user-stats">
                    <div class="stat-item">
                        <span class="stat-number"><?= number_format($sent_messages) ?></span>
                        <span class="stat-label"><?= __('sent_messages') ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?= number_format($received_messages) ?></span>
                        <span class="stat-label"><?= __('received_messages') ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?= number_format($joined_groups) ?></span>
                        <span class="stat-label"><?= __('joined_groups_count') ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?= number_format($created_groups) ?></span>
                        <span class="stat-label"><?= __('created_groups_count') ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Settings Tabs -->
        <div class="settings-tabs">
            <button class="tab-btn active" onclick="switchTab('account')">👤 <?= __('account') ?></button>
            <button class="tab-btn" onclick="switchTab('privacy')">🔒 <?= __('privacy') ?></button>
            <button class="tab-btn" onclick="switchTab('appearance')">🎨 <?= __('appearance') ?></button>
            <button class="tab-btn" onclick="switchTab('notifications')">🔔 <?= __('notifications') ?></button>
            <button class="tab-btn" onclick="switchTab('language')">🌐 <?= __('language') ?></button>
            <button class="tab-btn" onclick="switchTab('calls')">📞 تنظیمات تماس</button>
            <button class="tab-btn" onclick="switchTab('danger')">⚠️ <?= __('danger_zone') ?></button>
        </div>
        
        <!-- Account Settings -->
        <div id="accountTab" class="tab-content active">
            <div class="settings-section">
                <h3>📝 <?= __('change_account_info') ?></h3>
                
                <form id="accountForm" class="settings-form">
                    <div class="form-group">
                        <label for="newUsername"><?= __('new_username') ?>:</label>
                        <input type="text" id="newUsername" name="newUsername" 
                               value="<?= htmlspecialchars($user['username']) ?>" 
                               placeholder="<?= __('new_username') ?>" minlength="3" maxlength="50" required>
                        <small><?= __('username_hint') ?></small>
                    </div>
                    
                    <div class="form-group">
                        <label for="currentPassword"><?= __('current_password') ?>:</label>
                        <input type="password" id="currentPassword" name="currentPassword" 
                               placeholder="<?= __('current_password_confirm') ?>" required>
                    </div>
                    
                    <button type="submit" class="btn-primary">💾 <?= __('save_changes') ?></button>
                </form>
            </div>
            
            <div class="settings-section">
                <h3>🔑 <?= __('change_password') ?></h3>
                
                <form id="passwordForm" class="settings-form">
                    <div class="form-group">
                        <label for="oldPassword"><?= __('current_password') ?>:</label>
                        <input type="password" id="oldPassword" name="oldPassword" 
                               placeholder="<?= __('current_password') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="newPassword"><?= __('new_password') ?>:</label>
                        <input type="password" id="newPassword" name="newPassword" 
                               placeholder="<?= __('new_password') ?>" minlength="6" required>
                        <small><?= __('password_hint') ?></small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirmPassword"><?= __('confirm_new_password') ?>:</label>
                        <input type="password" id="confirmPassword" name="confirmPassword" 
                               placeholder="<?= __('confirm_new_password') ?>" required>
                    </div>
                    
                    <button type="submit" class="btn-primary">🔄 <?= __('change_password') ?></button>
                </form>
            </div>
        </div>
        
        <!-- Privacy Settings -->
        <div id="privacyTab" class="tab-content">
            <div class="settings-section">
                <h3>👁️ <?= __('online_status') ?></h3>
                <p><?= __('online_status_desc') ?></p>
                
                <div class="toggle-setting">
                    <label class="toggle-label">
                        <input type="checkbox" id="showOnlineStatus" 
                               <?= $user['show_online_status'] ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                        <span class="toggle-text"><?= __('show_online_status_to_others') ?></span>
                    </label>
                </div>
            </div>
        </div>
        
        <!-- Appearance Settings -->
        <div id="appearanceTab" class="tab-content">
            <div class="settings-section">
                <h3>🌙 <?= __('theme') ?></h3>
                <p><?= __('theme_desc') ?></p>
                
                <div class="theme-options">
                    <label class="theme-option">
                        <input type="radio" name="theme" value="light" 
                               <?= ($user['theme_preference'] ?? 'light') === 'light' ? 'checked' : '' ?>>
                        <div class="theme-preview light-preview">
                            <div class="theme-header"></div>
                            <div class="theme-content"></div>
                        </div>
                        <span>☀️ <?= __('theme_light') ?></span>
                    </label>
                    
                    <label class="theme-option">
                        <input type="radio" name="theme" value="dark" 
                               <?= ($user['theme_preference'] ?? 'light') === 'dark' ? 'checked' : '' ?>>
                        <div class="theme-preview dark-preview">
                            <div class="theme-header"></div>
                            <div class="theme-content"></div>
                        </div>
                        <span>🌙 <?= __('theme_dark') ?></span>
                    </label>
                </div>
            </div>
        </div>
        
        <!-- Notifications Settings -->
        <div id="notificationsTab" class="tab-content">
            <div class="settings-section">
                <h3>🔔 <?= __('browser_notifications') ?></h3>
                <p><?= __('browser_notifications_desc') ?></p>
                
                <div class="notification-status" id="notificationStatus">
                    <!-- وضعیت اعلان‌ها اینجا نمایش داده می‌شود -->
                </div>
                
                <div class="toggle-setting">
                    <label class="toggle-label">
                        <input type="checkbox" id="enableNotifications" 
                               <?= $user['enable_notifications'] ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                        <span class="toggle-text"><?= __('enable_browser_notifications') ?></span>
                    </label>
                </div>
                
                <button id="testNotification" class="btn-secondary">🧪 <?= __('test_notification') ?></button>
            </div>
        </div>
        
        <!-- Language Settings -->
        <div id="languageTab" class="tab-content">
            <div class="settings-section">
                <h3>🌐 <?= __('site_language') ?></h3>
                <p><?= __('site_language_desc') ?></p>
                
                <div class="language-options">
                    <label class="language-option">
                        <input type="radio" name="language" value="fa" 
                               <?= ($user['language_preference'] ?? 'fa') === 'fa' ? 'checked' : '' ?>>
                        <div class="language-flag">🇮🇷</div>
                        <span><?= __('persian') ?></span>
                    </label>
                    
                    <label class="language-option">
                        <input type="radio" name="language" value="en" 
                               <?= ($user['language_preference'] ?? 'fa') === 'en' ? 'checked' : '' ?>>
                        <div class="language-flag">🇺🇸</div>
                        <span><?= __('english') ?></span>
                    </label>
                </div>
            </div>
            </div>
        </div>
        
        <!-- Call Settings -->
        <div id="callsTab" class="tab-content">
            <div class="settings-section">
                <h3>📞 تنظیمات تماس</h3>
                <p>مدیریت کیفیت و تنظیمات تماس صوتی و تصویری</p>
                
                <div class="form-group">
                    <label for="bitrateRange">کیفیت تماس (بیت‌ریت):</label>
                    <div class="range-container">
                        <input type="range" id="bitrateRange" min="100" max="5000" step="100" 
                               value="<?= $user['preferred_bitrate'] ?? 500 ?>">
                        <span id="bitrateValue"><?= $user['preferred_bitrate'] ?? 500 ?> kbps</span>
                    </div>
                    <small>مقدار بیشتر = کیفیت بالاتر و مصرف اینترنت بیشتر</small>
                </div>
                
                <div class="form-group">
                    <label>تست صدای زنگ:</label>
                    <button id="testRingtoneBtn" class="btn-secondary" onclick="testRingtone()">🔔 پخش صدای زنگ</button>
                </div>
            </div>
        </div>
        
        <script>
        let testAudio = null;
        let testAudioCtx = null;
        let testBeepInterval = null;

        function testRingtone() {
            // توقف پخش قبلی
            if (testAudio || testAudioCtx) {
                if (testAudio) {
                    testAudio.pause();
                    testAudio = null;
                }
                if (testBeepInterval) {
                    clearInterval(testBeepInterval);
                    testBeepInterval = null;
                }
                if (testAudioCtx) {
                    testAudioCtx.close();
                    testAudioCtx = null;
                }
                document.getElementById('testRingtoneBtn').textContent = '🔔 پخش صدای زنگ';
                return;
            }

            // شروع پخش جدید
            document.getElementById('testRingtoneBtn').textContent = '⏹ توقف';
            testAudio = new Audio('assets/ringtone.mp3');
            testAudio.loop = true;
            
            testAudio.play().then(() => {
                console.log('Playing from file');
            }).catch(e => {
                console.log('File play failed, using AudioContext:', e);
                testAudio = null; // Clear audio object
                playTestBeep();
            });
        }

        function playTestBeep() {
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (!AudioContext) {
                    alert('مرورگر شما از پخش صدا پشتیبانی نمی‌کند');
                    return;
                }
                
                testAudioCtx = new AudioContext();
                
                const playTone = () => {
                    if (!testAudioCtx) return;
                    const osc = testAudioCtx.createOscillator();
                    const gain = testAudioCtx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(440, testAudioCtx.currentTime); 
                    osc.frequency.setValueAtTime(880, testAudioCtx.currentTime + 0.5);
                    gain.gain.setValueAtTime(0.1, testAudioCtx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, testAudioCtx.currentTime + 1);
                    osc.connect(gain);
                    gain.connect(testAudioCtx.destination);
                    osc.start();
                    osc.stop(testAudioCtx.currentTime + 1);
                };

                playTone();
                testBeepInterval = setInterval(playTone, 1500);
                
            } catch (e) {
                alert('خطا در پخش صدا: ' + e);
            }
        }
        </script>
        
        <!-- Danger Zone -->
        <div id="dangerTab" class="tab-content">
            <div class="settings-section danger-zone">
                <h3>⚠️ <?= __('danger_zone') ?></h3>
                <p class="danger-warning">
                    ⚠️ <?= __('danger_zone_warning') ?>
                </p>
                
                <div class="danger-action">
                    <h4>🗑️ <?= __('delete_account') ?></h4>
                    <p><?= __('delete_account_desc') ?></p>
                    <button id="deleteAccountBtn" class="btn-danger">🗑️ <?= __('delete_account') ?></button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay" style="display: none;">
        <div class="loading-spinner"></div>
        <p>در حال پردازش...</p>
    </div>
    

    
    <!-- Confirmation Modal -->
    <div id="confirmModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h3 id="confirmTitle">تأیید عملیات</h3>
            <p id="confirmMessage">آیا از انجام این عملیات اطمینان دارید؟</p>
            <div class="modal-actions">
                <button id="confirmYes" class="btn-danger">بله</button>
                <button id="confirmNo" class="btn-secondary">خیر</button>
            </div>
        </div>
    </div>
    

    
    <!-- Notification Container -->
    <div id="notificationContainer"></div>

    <script src="assets/webrtc.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // راه‌اندازی WebRTC برای دریافت تماس در تنظیمات
            if (typeof initWebRTC === 'function') {
                initWebRTC(0, <?= $_SESSION['user_id'] ?>);
            }
        });
    </script>
    <script src="assets/settings.js"></script>
    <?php include 'includes/webrtc_loader.php'; ?>
</body>
</html>