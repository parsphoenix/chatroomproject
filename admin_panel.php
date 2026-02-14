<?php
/**
 * پنل مدیریت ادمین
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

// چک دسترسی ادمین
try {
    $stmt = $pdo->prepare("SELECT user_role, theme_preference FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_data || $user_data['user_role'] !== 'admin') {
        header('Location: dashboard.php');
        exit;
    }
    $user_theme = $user_data['theme_preference'] ?: 'light';
} catch (PDOException $e) {
    die(__('error_check_permission') . ': ' . $e->getMessage());
}

// دریافت آمار کلی
try {
    // تعداد کل کاربران
    $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users WHERE user_role = 'user'");
    $total_users = $stmt->fetch()['total_users'];
    
    // تعداد کاربران ممنوع
    $stmt = $pdo->query("SELECT COUNT(DISTINCT banned_user_id) as banned_users FROM user_bans WHERE is_active = TRUE");
    $banned_users = $stmt->fetch()['banned_users'];
    
    // تعداد کاربران آنلاین
    $stmt = $pdo->query("SELECT COUNT(*) as online_users FROM users WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) AND user_role = 'user'");
    $online_users = $stmt->fetch()['online_users'];
    
    // تعداد پیام‌های امروز
    $stmt = $pdo->query("SELECT COUNT(*) as today_messages FROM messages WHERE DATE(created_at) = CURDATE()");
    $today_messages = $stmt->fetch()['today_messages'];
    
} catch (PDOException $e) {
    die(__('error_load_stats') . ': ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html dir="<?= get_direction() ?>" lang="<?= get_lang_code() ?>" data-theme="<?= $user_theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل مدیریت - وب‌چت</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/chat-fixes.css">
    <script>
        // اعمال تم ذخیره شده بلافاصله برای جلوگیری از پرش تصویر
        (function() {
            const savedTheme = localStorage.getItem('theme') || '<?= $user_theme ?>' || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    <style>
        * {
            font-family: 'Vazir', 'Tahoma', sans-serif !important;
        }
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: var(--card-bg);
            color: var(--text-main);
            border-radius: 15px;
            box-shadow: var(--shadow-main);
            margin-top: 20px;
        }
        
        .admin-header {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: var(--shadow-main);
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .stat-label {
            font-size: 1.1em;
            opacity: 0.9;
        }
        
        .admin-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .admin-tab {
            padding: 15px 25px;
            background: var(--input-bg);
            color: var(--text-muted);
            border: none;
            border-radius: 10px 10px 0 0;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .admin-tab.active {
            background: #007bff;
            color: white;
        }
        
        .tab-content {
            display: none;
            padding: 20px 0;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .search-section {
            background: var(--input-bg);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .search-input {
            width: 100%;
            padding: 15px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 16px;
            margin-bottom: 15px;
            background: var(--card-bg);
            color: var(--text-main);
        }
        
        .user-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-main);
            color: var(--text-main);
        }

        .user-details {
            color: var(--text-muted);
            font-size: 0.9em;
        }
        
        .user-name {
            font-weight: bold;
            font-size: 1.2em;
            margin-bottom: 5px;
        }
        
        .user-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
            margin-left: 10px;
        }
        
        .status-online {
            background: var(--success-bg);
            color: var(--success-text);
        }
        
        .status-offline {
            background: var(--error-bg);
            color: var(--error-text);
        }
        
        .status-banned {
            background: var(--error-bg);
            color: var(--error-text);
        }
        
        .admin-actions {
            display: flex;
            gap: 10px;
        }
        
        .admin-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.2s;
        }
        
        .btn-ban {
            background: #dc3545;
            color: white;
        }
        
        .btn-unban {
            background: #28a745;
            color: white;
        }
        
        .btn-view {
            background: #007bff;
            color: white;
        }
        
        .admin-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .ban-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        }
        
        .ban-modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            max-width: 500px;
            width: 90%;
        }
        
        .ban-reason {
            width: 100%;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 10px;
            resize: vertical;
            min-height: 100px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>🛡️ پنل مدیریت سیستم</h1>
            <p>خوش آمدید، <?= htmlspecialchars($_SESSION['username']) ?></p>
            <a href="dashboard.php" style="color: white; text-decoration: none; margin-top: 10px; display: inline-block;">🔙 بازگشت به داشبورد</a>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= number_format($total_users) ?></div>
                <div class="stat-label">کل کاربران</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= number_format($online_users) ?></div>
                <div class="stat-label">کاربران آنلاین</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= number_format($banned_users) ?></div>
                <div class="stat-label">کاربران ممنوع</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= number_format($today_messages) ?></div>
                <div class="stat-label">پیام‌های امروز</div>
            </div>
        </div>
        
        <div class="admin-tabs">
            <button class="admin-tab active" onclick="switchAdminTab('users')">مدیریت کاربران</button>
            <button class="admin-tab" onclick="switchAdminTab('banned')">کاربران ممنوع</button>
            <button class="admin-tab" onclick="switchAdminTab('logs')">لاگ فعالیت‌ها</button>
        </div>
        
        <div id="usersTab" class="tab-content active">
            <div class="search-section">
                <h3>جستجو و مدیریت کاربران</h3>
                <input type="text" class="search-input" id="userSearchInput" placeholder="نام کاربری را جستجو کنید...">
                <div id="userSearchResults"></div>
            </div>
        </div>
        
        <div id="bannedTab" class="tab-content">
            <div class="search-section">
                <h3>کاربران ممنوع شده</h3>
                <div id="bannedUsersList">
                    <div class="loading">در حال بارگذاری...</div>
                </div>
            </div>
        </div>
        
        <div id="logsTab" class="tab-content">
            <div class="search-section">
                <h3>لاگ فعالیت‌های ادمین</h3>
                <div id="adminLogsList">
                    <div class="loading">در حال بارگذاری...</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal ممنوع کردن کاربر -->
    <div id="banModal" class="ban-modal">
        <div class="ban-modal-content">
            <h3>ممنوع کردن کاربر</h3>
            <p>آیا مطمئن هستید که می‌خواهید کاربر <strong id="banUsername"></strong> را ممنوع کنید؟</p>
            <textarea class="ban-reason" id="banReason" placeholder="دلیل ممنوعیت را وارد کنید..."></textarea>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button class="admin-btn btn-ban" onclick="confirmBan()">ممنوع کردن</button>
                <button class="admin-btn" onclick="closeBanModal()" style="background: #6c757d; color: white;">لغو</button>
            </div>
        </div>
    </div>
    
    <!-- Notification Container -->
    <div id="notificationContainer"></div>
    
    <?php include 'includes/webrtc_loader.php'; ?>
    
    <script>
        let currentBanUserId = null;
        let searchTimeout = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            // بارگذاری تم
            const savedTheme = document.documentElement.getAttribute('data-theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
            localStorage.setItem('theme', savedTheme);

            loadBannedUsers();
            loadAdminLogs();
            
            // Event listener برای جستجو
            document.getElementById('userSearchInput').addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const query = this.value.trim();
                
                if (query.length >= 2) {
                    searchTimeout = setTimeout(() => searchUsers(query), 500);
                } else {
                    document.getElementById('userSearchResults').innerHTML = '';
                }
            });
        });
        
        // تغییر تب
        function switchAdminTab(tabName) {
            // حذف کلاس active از همه تب‌ها
            document.querySelectorAll('.admin-tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            // اضافه کردن کلاس active به تب انتخاب شده
            event.target.classList.add('active');
            document.getElementById(tabName + 'Tab').classList.add('active');
        }
        
        // جستجوی کاربران
        async function searchUsers(query) {
            try {
                const response = await fetch('api/admin_search_users.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `query=${encodeURIComponent(query)}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    displaySearchResults(data.users);
                } else {
                    document.getElementById('userSearchResults').innerHTML = 
                        '<div class="alert error">' + data.message + '</div>';
                }
            } catch (error) {
                console.error('خطا در جستجو:', error);
                document.getElementById('userSearchResults').innerHTML = 
                    '<div class="alert error">خطا در جستجوی کاربران</div>';
            }
        }
        
        // نمایش نتایج جستجو
        function displaySearchResults(users) {
            const container = document.getElementById('userSearchResults');
            
            if (users.length === 0) {
                container.innerHTML = '<div class="alert info">کاربری یافت نشد</div>';
                return;
            }
            
            let html = '';
            users.forEach(user => {
                const statusClass = user.is_banned ? 'status-banned' : 
                                  (user.is_online ? 'status-online' : 'status-offline');
                const statusText = user.is_banned ? 'ممنوع' : 
                                 (user.is_online ? 'آنلاین' : 'آفلاین');
                
                html += `
                    <div class="user-card">
                        <div class="user-info">
                            <div class="user-name">${user.username}</div>
                            <div class="user-details">
                                آخرین فعالیت: ${user.last_seen_formatted}
                                <span class="user-status ${statusClass}">${statusText}</span>
                            </div>
                        </div>
                        <div class="admin-actions">
                            ${user.is_banned ? 
                                `<button class="admin-btn btn-unban" onclick="unbanUser(${user.id}, '${user.username}')">رفع ممنوعیت</button>` :
                                `<button class="admin-btn btn-ban" onclick="showBanModal(${user.id}, '${user.username}')">ممنوع کردن</button>`
                            }
                            <button class="admin-btn btn-view" onclick="viewUserDetails(${user.id})">جزئیات</button>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        // نمایش modal ممنوع کردن
        function showBanModal(userId, username) {
            currentBanUserId = userId;
            document.getElementById('banUsername').textContent = username;
            document.getElementById('banReason').value = '';
            document.getElementById('banModal').style.display = 'flex';
        }
        
        // بستن modal
        function closeBanModal() {
            document.getElementById('banModal').style.display = 'none';
            currentBanUserId = null;
        }
        
        // تایید ممنوع کردن
        async function confirmBan() {
            if (!currentBanUserId) return;
            
            const reason = document.getElementById('banReason').value.trim();
            if (!reason) {
                showNotification('لطفاً دلیل ممنوعیت را وارد کنید', 'error');
                return;
            }
            
            try {
                const response = await fetch('api/admin_ban_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `user_id=${currentBanUserId}&reason=${encodeURIComponent(reason)}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('کاربر با موفقیت ممنوع شد', 'success');
                    closeBanModal();
                    // رفرش نتایج جستجو
                    const query = document.getElementById('userSearchInput').value;
                    if (query) {
                        searchUsers(query);
                    }
                } else {
                    showNotification('خطا: ' + data.message, 'error');
                }
            } catch (error) {
                console.error('خطا در ممنوع کردن:', error);
                showNotification('خطا در ممنوع کردن کاربر', 'error');
            }
        }
        
        // رفع ممنوعیت
        async function unbanUser(userId, username) {
            if (!confirm(`آیا مطمئن هستید که می‌خواهید ممنوعیت کاربر ${username} را لغو کنید؟`)) return;
            
            try {
                const response = await fetch('api/admin_unban_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `user_id=${userId}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('ممنوعیت کاربر با موفقیت لغو شد', 'success');
                    // رفرش نتایج جستجو
                    const query = document.getElementById('userSearchInput').value;
                    if (query) {
                        searchUsers(query);
                    }
                } else {
                    showNotification('خطا: ' + data.message, 'error');
                }
            } catch (error) {
                console.error('خطا در لغو ممنوعیت:', error);
                showNotification('خطا در لغو ممنوعیت', 'error');
            }
        }
        
        // بارگذاری کاربران ممنوع
        async function loadBannedUsers() {
            try {
                const response = await fetch('api/admin_get_banned_users.php');
                const data = await response.json();
                
                if (data.success) {
                    displayBannedUsers(data.users);
                } else {
                    document.getElementById('bannedUsersList').innerHTML = 
                        '<div class="alert error">' + data.message + '</div>';
                }
            } catch (error) {
                console.error('خطا در بارگذاری کاربران ممنوع:', error);
                document.getElementById('bannedUsersList').innerHTML = 
                    '<div class="alert error">خطا در بارگذاری اطلاعات</div>';
            }
        }
        
        // نمایش کاربران ممنوع
        function displayBannedUsers(users) {
            const container = document.getElementById('bannedUsersList');
            
            if (users.length === 0) {
                container.innerHTML = '<div class="alert info">هیچ کاربر ممنوعی وجود ندارد</div>';
                return;
            }
            
            let html = '';
            users.forEach(user => {
                html += `
                    <div class="user-card">
                        <div class="user-info">
                            <div class="user-name">${user.username}</div>
                            <div class="user-details">
                                دلیل: ${user.ban_reason || 'مشخص نشده'}<br>
                                تاریخ ممنوعیت: ${user.banned_at_formatted}
                            </div>
                        </div>
                        <div class="admin-actions">
                            <button class="admin-btn btn-unban" onclick="unbanUser(${user.id}, '${user.username}')">رفع ممنوعیت</button>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        // بارگذاری لاگ‌ها
        async function loadAdminLogs() {
            try {
                const response = await fetch('api/admin_get_logs.php');
                const data = await response.json();
                
                if (data.success) {
                    displayAdminLogs(data.logs);
                } else {
                    document.getElementById('adminLogsList').innerHTML = 
                        '<div class="alert error">' + data.message + '</div>';
                }
            } catch (error) {
                console.error('خطا در بارگذاری لاگ‌ها:', error);
                document.getElementById('adminLogsList').innerHTML = 
                    '<div class="alert error">خطا در بارگذاری اطلاعات</div>';
            }
        }
        
        // نمایش لاگ‌ها
        function displayAdminLogs(logs) {
            const container = document.getElementById('adminLogsList');
            
            if (logs.length === 0) {
                container.innerHTML = '<div class="alert info">هیچ فعالیتی ثبت نشده</div>';
                return;
            }
            
            let html = '';
            logs.forEach(log => {
                const actionText = {
                    'ban_user': 'ممنوع کردن کاربر',
                    'unban_user': 'لغو ممنوعیت کاربر',
                    'view_users': 'مشاهده کاربران'
                };
                
                html += `
                    <div class="user-card">
                        <div class="user-info">
                            <div class="user-name">${actionText[log.action_type] || log.action_type}</div>
                            <div class="user-details">
                                ${log.target_username ? `کاربر هدف: ${log.target_username}` : ''}<br>
                                ${log.action_details ? `جزئیات: ${log.action_details}` : ''}<br>
                                تاریخ: ${log.created_at_formatted}
                            </div>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        // نمایش نوتیفیکیشن
        function showNotification(message, type = 'info') {
            const container = document.getElementById('notificationContainer');
            if (!container) return;
            
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <div>${message}</div>
                <button onclick="this.parentElement.remove()" style="background: none; border: none; float: left; cursor: pointer;">×</button>
            `;
            
            container.appendChild(notification);
            
            // نمایش انیمیشن
            setTimeout(() => notification.classList.add('show'), 100);
            
            // حذف خودکار بعد از 5 ثانیه
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 5000);
        }
    </script>
    <?php include 'includes/webrtc_loader.php'; ?>
</body>
</html>