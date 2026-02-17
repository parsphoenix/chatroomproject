<?php
/**
 * صفحه اصلی - جستجو و لیست کاربران
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

// Check Auth (Session or Remember Me)
if (!checkAuth($pdo)) {
    header('Location: login.php');
    exit;
}

require_once 'check_ban_middleware.php';

// چک ممنوعیت کاربر
checkUserBan($pdo, $_SESSION['user_id']);

// آپدیت آخرین فعالیت کاربر جاری
try {
    $stmt = $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
} catch (PDOException $e) {
    // خطا در آپدیت - ادامه بده
}

// دریافت اطلاعات کاربر و تم
$user_theme = 'light';
$is_admin = false;
try {
    $stmt = $pdo->prepare("SELECT user_role, theme_preference FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user_data) {
        $user_theme = $user_data['theme_preference'] ?: 'light';
        $is_admin = ($user_data['user_role'] === 'admin');
    }
} catch (PDOException $e) {
    // خطا - ادامه بده
}
?>
<!DOCTYPE html>
<html dir="<?= get_direction() ?>" lang="<?= get_lang_code() ?>" data-theme="<?= $user_theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('dashboard_title') ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/minimized-call.css">
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <div class="dashboard-nav">
                <h1>💬 <?= __('app_name') ?></h1>
                <div class="user-info">
                    <div class="theme-toggle-nav" style="display: inline-block; margin-left: 15px;">
                        <button id="themeToggle" class="logout-btn" title="<?= __('toggle_theme') ?>">🌙</button>
                    </div>
                    <div class="lang-selector-nav" style="display: inline-block; margin-left: 15px;">
                        <a href="?lang=fa" class="lang-link" style="color: <?= get_lang_code() == 'fa' ? '#4facfe' : '#666' ?>; text-decoration: none; font-weight: bold;">FA</a>
                        |
                        <a href="?lang=en" class="lang-link" style="color: <?= get_lang_code() == 'en' ? '#4facfe' : '#666' ?>; text-decoration: none; font-weight: bold;">EN</a>
                    </div>
                    <span><?= __('welcome_user', ['username' => htmlspecialchars($_SESSION['username'])]) ?></span>
                    <a href="settings.php" class="logout-btn"><?= __('settings') ?></a>
                    <a href="groups.php" class="logout-btn"><?= __('groups') ?></a>
                    <a href="blocked_users.php" class="logout-btn"><?= __('blocked_users') ?></a>
                    <?php
                    if ($is_admin) {
                        echo '<a href="admin_panel.php" class="logout-btn" style="background: #e74c3c;">' . __('admin_panel') . '</a>';
                    }
                    ?>
                    <a href="logout.php" class="logout-btn"><?= __('logout') ?></a>
                </div>
            </div>
        </div>
        
        <?php require_once 'includes/webrtc_loader.php'; ?>
        
        <div class="dashboard-content">
            <div class="dashboard-layout">
                <!-- سایدبار چت‌های اخیر -->
                <div class="sidebar">
                    <div class="sidebar-section">
                        <h3>💬 <?= __('recent_chats') ?></h3>
                        <div id="recentChats" class="recent-chats-list">
                            <div class="alert info"><?= __('no_recent_chats') ?></div>
                        </div>
                        <div id="recentChatsPagination" class="pagination-container"></div>
                    </div>
                    
                    <div class="sidebar-section">
                        <h3>⚙️ <?= __('settings') ?></h3>
                        <div class="settings-item">
                            <label class="toggle-switch">
                                <input type="checkbox" id="publicToggle">
                                <span class="slider"></span>
                            </label>
                            <span><?= __('public_visibility') ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- محتوای اصلی -->
                <div class="main-content">
                    <div class="search-section">
                        <h2>🔍 <?= __('search_users') ?></h2>
                        <div class="search-tabs">
                            <button class="tab-btn active" onclick="switchTab('search')"><?= __('search') ?></button>
                            <button class="tab-btn" onclick="switchTab('public')"><?= __('public_users') ?></button>
                        </div>
                        
                        <div id="searchTab" class="tab-content active">
                            <div class="search-box">
                                <input type="text" id="searchInput" placeholder="<?= __('enter_username_search') ?>">
                            </div>
                            <div id="searchResults" class="users-list">
                            <!-- نتایج جستجو اینجا نمایش داده می‌شود -->
                        </div>
                        </div>
                        
                        <div id="publicTab" class="tab-content">
                            <div id="publicUsers" class="users-list">
                                <div class="loading">در حال بارگذاری...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modals are loaded via webrtc_loader.php -->
    <div id="notificationContainer"></div>

    <?php include 'includes/webrtc_loader.php'; ?>

    <script>
        let searchTimeout;
        let currentTab = 'search';
        let recentChatsPage = 1;
        
        document.addEventListener('DOMContentLoaded', function() {
            // بارگذاری تم
            const savedTheme = localStorage.getItem('theme') || document.documentElement.getAttribute('data-theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
            updateThemeIcon(savedTheme);

            // بارگذاری اولیه
            loadRecentChats(1);
            loadPublicUsers();
            loadUserSettings();
            
            // Event listeners
            setupEventListeners();
            
            // بروزرسانی منظم
            setInterval(() => loadRecentChats(recentChatsPage), 5000); // هر 5 ثانیه
            setInterval(updateActivity, 30000); // هر 30 ثانیه
        });

        function updateThemeIcon(theme) {
            const btn = document.getElementById('themeToggle');
            if (btn) {
                btn.innerHTML = theme === 'dark' ? '☀️' : '🌙';
            }
        }
        
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
        
        function setupEventListeners() {
            const searchInput = document.getElementById('searchInput');
            const publicToggle = document.getElementById('publicToggle');
            const themeToggle = document.getElementById('themeToggle');
            
            // تغییر تم
            if (themeToggle) {
                themeToggle.addEventListener('click', function() {
                    const currentTheme = document.documentElement.getAttribute('data-theme');
                    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                    document.documentElement.setAttribute('data-theme', newTheme);
                    localStorage.setItem('theme', newTheme);
                    updateThemeIcon(newTheme);
                    
                    // ذخیره در دیتابیس (اختیاری)
                    updateThemePreference(newTheme);
                });
            }

            // جستجوی کاربران
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const query = this.value.trim();
                
                if (query.length < 2) {
                    document.getElementById('searchResults').innerHTML = 
                        '<div class="alert info">برای جستجوی کاربران، حداقل 2 کاراکتر تایپ کنید</div>';
                    return;
                }
                
                document.getElementById('searchResults').innerHTML = '<div class="loading"></div>';
                
                searchTimeout = setTimeout(() => {
                    searchUsers(query);
                }, 500);
            });
            
            // تغییر وضعیت عمومی
            publicToggle.addEventListener('change', function() {
                updatePublicStatus(this.checked);
            });
        }
        
        // تابع جستجوی کاربران
        function searchUsers(query) {
            fetch('api/search_users.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'query=' + encodeURIComponent(query)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayUsers(data.users, 'searchResults');
                } else {
                    document.getElementById('searchResults').innerHTML = 
                        '<div class="alert error">' + data.message + '</div>';
                }
            })
            .catch(error => {
                console.error('خطا در جستجو:', error);
                document.getElementById('searchResults').innerHTML = 
                    '<div class="alert error">خطا در جستجو. لطفاً مجدداً تلاش کنید.</div>';
            });
        }
        
        // بارگذاری کاربران عمومی
        function loadPublicUsers() {
            fetch('api/get_public_users.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayUsers(data.users, 'publicUsers');
                } else {
                    document.getElementById('publicUsers').innerHTML = 
                        '<div class="alert info">کاربر عمومی‌ای یافت نشد</div>';
                }
            })
            .catch(error => {
                console.error('خطا در بارگذاری کاربران عمومی:', error);
            });
        }
        
        // بارگذاری چت‌های اخیر
        function loadRecentChats(page = 1) {
            recentChatsPage = page;
            fetch('api/get_recent_chats.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'page=' + page
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayRecentChats(data.chats);
                    updatePagination(data.current_page, data.total_pages);
                }
            })
            .catch(error => {
                console.error('خطا در بارگذاری چت‌های اخیر:', error);
            });
        }
        
        // نمایش چت‌های اخیر
        function displayRecentChats(chats) {
            const container = document.getElementById('recentChats');
            
            if (!chats || chats.length === 0) {
                container.innerHTML = '<div class="alert info">هنوز چتی نداشته‌اید</div>';
                return;
            }
            
            let html = '';
            chats.forEach(chat => {
                const avatar = chat.username.charAt(0).toUpperCase();
                const unreadBadge = chat.unread_count > 0 ? 
                    `<span class="unread-badge">${chat.unread_count}</span>` : '';
                const isOnline = chat.is_online;
                
                html += `
                    <div class="recent-chat-item">
                        <div class="chat-content" onclick="openChat('${chat.username}')">
                            <div class="user-avatar-container">
                                <div class="user-avatar small">${avatar}</div>
                                <span class="status-indicator ${isOnline ? 'online' : 'offline'}"></span>
                            </div>
                            <div class="chat-info">
                                <h4>${chat.username} ${unreadBadge}</h4>
                                <p class="last-message">${chat.last_message || 'پیامی موجود نیست'}</p>
                            </div>
                        </div>
                        <div class="chat-actions">
                            <button class="delete-chat-btn" onclick="deleteChatHistory('${chat.username}')" title="حذف کامل چت">
                                🗑️
                            </button>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        // بروزرسانی صفحه‌بندی
        function updatePagination(currentPage, totalPages) {
            const container = document.getElementById('recentChatsPagination');
            if (!container) return;

            if (totalPages <= 1) {
                container.innerHTML = '';
                return;
            }

            let html = `
                <button class="pagination-btn" onclick="loadRecentChats(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>◀</button>
                <span class="page-info">${currentPage} از ${totalPages}</span>
                <button class="pagination-btn" onclick="loadRecentChats(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>▶</button>
            `;
            container.innerHTML = html;
        }
        
        // نمایش لیست کاربران (شبکه‌ای)
        function displayUsers(users, containerId) {
            const container = document.getElementById(containerId);
            
            if (!users || users.length === 0) {
                container.innerHTML = '<div class="alert info">کاربری یافت نشد</div>';
                return;
            }
            
            let html = '';
            users.forEach(user => {
                const isOnline = user.is_online;
                const statusText = isOnline ? 'آنلاین' : 'آفلاین';
                const avatar = user.username.charAt(0).toUpperCase();
                
                html += `
                    <div class="user-item" onclick="openChat('${user.username}')">
                        <div class="user-info-item">
                            <div class="user-avatar">${avatar}</div>
                            <div class="user-details">
                                <h3>${user.username}</h3>
                                <div class="user-status">
                                    <span class="${isOnline ? 'online' : 'offline'}-indicator"></span>
                                    ${statusText}
                                </div>
                            </div>
                        </div>
                        <div class="user-actions">
                            <button class="chat-btn" onclick="event.stopPropagation(); openChat('${user.username}')">
                                💬 چت
                            </button>
                            <button class="block-btn" onclick="event.stopPropagation(); blockUser('${user.username}')">
                                🚫 بلاک
                            </button>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        // تعویض تب
        function switchTab(tabName) {
            // حذف کلاس active از همه تب‌ها
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            // اضافه کردن کلاس active به تب انتخاب شده
            event.target.classList.add('active');
            document.getElementById(tabName + 'Tab').classList.add('active');
            
            currentTab = tabName;
            
            if (tabName === 'public') {
                loadPublicUsers();
            }
        }
        
        // باز کردن صفحه چت
        function openChat(username) {
            window.location.href = 'chat.php?user=' + encodeURIComponent(username);
        }
        
        // حذف کامل تاریخچه چت
        async function deleteChatHistory(username) {
            if (!confirm(`آیا مطمئن هستید که می‌خواهید تمام پیام‌های چت با ${username} را حذف کنید؟\n\nاین عمل غیرقابل بازگشت است!`)) {
                return;
            }
            
            try {
                const response = await fetch('api/delete_chat_history.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `username=${encodeURIComponent(username)}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('تاریخچه چت با موفقیت حذف شد', 'success');
                    // رفرش چت‌های اخیر
                    loadRecentChats();
                } else {
                    showNotification('خطا: ' + data.message, 'error');
                }
            } catch (error) {
                console.error('خطا در حذف تاریخچه چت:', error);
                showNotification('خطا در حذف تاریخچه چت', 'error');
            }
        }
        
        // بلاک کردن کاربر
        function blockUser(username) {
            if (confirm('آیا مطمئن هستید که می‌خواهید این کاربر را بلاک کنید؟')) {
                fetch('api/block_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'username=' + encodeURIComponent(username)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('کاربر با موفقیت بلاک شد');
                        // بروزرسانی لیست‌ها
                        if (currentTab === 'search') {
                            const query = document.getElementById('searchInput').value.trim();
                            if (query.length >= 2) {
                                searchUsers(query);
                            }
                        } else {
                            loadPublicUsers();
                        }
                    } else {
                        alert('خطا: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('خطا در بلاک کردن:', error);
                    alert('خطا در بلاک کردن کاربر');
                });
            }
        }
        
        // بارگذاری تنظیمات کاربر
        function loadUserSettings() {
            fetch('api/get_user_settings.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('publicToggle').checked = data.is_public;
                }
            })
            .catch(error => {
                console.error('خطا در بارگذاری تنظیمات:', error);
            });
        }
        
        // بروزرسانی وضعیت عمومی
        function updatePublicStatus(isPublic) {
            fetch('api/update_public_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'is_public=' + (isPublic ? '1' : '0')
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert('خطا در بروزرسانی تنظیمات: ' + data.message);
                    // برگرداندن وضعیت قبلی
                    document.getElementById('publicToggle').checked = !isPublic;
                }
            })
            .catch(error => {
                console.error('خطا در بروزرسانی:', error);
                alert('خطا در بروزرسانی تنظیمات');
                document.getElementById('publicToggle').checked = !isPublic;
            });
        }
        
        // آپدیت فعالیت
        function updateActivity() {
            fetch('api/update_activity.php', {
                method: 'POST'
            });
        }
        
        // نمایش نوتیفیکیشن
        function showNotification(message, type = 'info') {
            // ایجاد container اگر وجود ندارد
            let container = document.getElementById('notificationContainer');
            if (!container) {
                container = document.createElement('div');
                container.id = 'notificationContainer';
                container.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    z-index: 10000;
                    max-width: 400px;
                `;
                document.body.appendChild(container);
            }
            
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.style.cssText = `
                background: white;
                padding: 15px;
                margin-bottom: 10px;
                border-radius: 8px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                border-left: 4px solid #2196f3;
                opacity: 0;
                transform: translateX(100%);
                transition: all 0.3s ease;
            `;
            
            // تنظیم رنگ بر اساس نوع
            if (type === 'success') {
                notification.style.borderLeftColor = '#4caf50';
            } else if (type === 'warning') {
                notification.style.borderLeftColor = '#ff9800';
            } else if (type === 'error') {
                notification.style.borderLeftColor = '#f44336';
            }
            
            notification.innerHTML = `
                <div>${message}</div>
                <button onclick="this.parentElement.remove()" style="background: none; border: none; float: left; cursor: pointer; font-size: 18px;">×</button>
            `;
            
            container.appendChild(notification);
            
            // نمایش انیمیشن
            setTimeout(() => {
                notification.style.opacity = '1';
                notification.style.transform = 'translateX(0)';
            }, 100);
            
            // حذف خودکار بعد از 5 ثانیه
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => notification.remove(), 300);
            }, 5000);
        }
    </script>
</body>
</html>