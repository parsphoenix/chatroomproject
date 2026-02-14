<?php
/**
 * صفحه مدیریت گروه‌ها
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

// آپدیت آخرین فعالیت کاربر جاری
try {
    $stmt = $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
} catch (PDOException $e) {
    // خطا در آپدیت - ادامه بده
}

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
?>
<!DOCTYPE html>
<html dir="<?= get_direction() ?>" lang="<?= get_lang_code() ?>" data-theme="<?= $user_theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('groups_title') ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <div class="dashboard-nav">
                <h1>👥 <?= __('groups') ?></h1>
                <div class="user-info">
                    <div class="theme-toggle-nav" style="display: inline-block; margin-left: 15px;">
                        <button id="themeToggle" class="logout-btn" title="<?= __('toggle_theme') ?>"><?= $user_theme === 'dark' ? '☀️' : '🌙' ?></button>
                    </div>
                    <div class="lang-selector-nav" style="display: inline-block; margin-left: 15px;">
                        <a href="?lang=fa" class="lang-link" style="color: <?= get_lang_code() == 'fa' ? '#4facfe' : '#666' ?>; text-decoration: none; font-weight: bold;">FA</a>
                        |
                        <a href="?lang=en" class="lang-link" style="color: <?= get_lang_code() == 'en' ? '#4facfe' : '#666' ?>; text-decoration: none; font-weight: bold;">EN</a>
                    </div>
                    <span><?= __('welcome_user', ['username' => htmlspecialchars($_SESSION['username'])]) ?></span>
                    <a href="dashboard.php" class="logout-btn"><?= __('dashboard') ?></a>
                    <a href="logout.php" class="logout-btn"><?= __('logout') ?></a>
                </div>
            </div>
        </div>
        
        <?php require_once 'includes/webrtc_loader.php'; ?>
        
        <div class="dashboard-content">
            <div class="dashboard-layout">
                <!-- سایدبار گروه‌ها -->
                <div class="sidebar">
                    <div class="sidebar-section">
                        <h3>📋 <?= __('my_groups') ?></h3>
                        <div id="myGroups" class="groups-list">
                            <div class="alert info"><?= __('loading') ?></div>
                        </div>
                        
                        <button class="create-group-btn" onclick="showCreateGroupModal()">
                            <?= __('create_group') ?>
                        </button>
                    </div>
                    
                    <div class="sidebar-section">
                        <h3>📨 <?= __('invitations') ?></h3>
                        <div id="groupInvitations" class="invitations-list">
                            <div class="alert info"><?= __('no_invitations') ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- محتوای اصلی -->
                <div class="main-content">
                    <div class="groups-section">
                        <h2>👥 <?= __('joined_groups') ?></h2>
                        <div id="joinedGroups" class="groups-grid">
                            <div class="loading"><?= __('loading') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal ساخت گروه -->
    <div id="createGroupModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><?= __('create_group_modal_title') ?></h3>
                <button class="close-btn" onclick="hideCreateGroupModal()">×</button>
            </div>
            <form id="createGroupForm" class="modal-form">
                <div class="form-group">
                    <label for="groupName"><?= __('group_name') ?>:</label>
                    <input type="text" id="groupName" name="groupName" placeholder="<?= __('enter_group_name') ?>" required maxlength="100">
                </div>
                <div class="form-group">
                    <label for="groupDescription"><?= __('group_description') ?>:</label>
                    <textarea id="groupDescription" name="groupDescription" placeholder="<?= __('enter_group_description') ?>" maxlength="500"></textarea>
                </div>
                <button type="submit" class="auth-btn"><?= __('submit_create_group') ?></button>
            </form>
        </div>
    </div>
    
    <!-- Modal دعوت کاربران -->
    <div id="inviteUsersModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>دعوت کاربران به گروه</h3>
                <button class="close-btn" onclick="hideInviteUsersModal()">×</button>
            </div>
            <div class="modal-body">
                <div class="search-box">
                    <input type="text" id="inviteSearchInput" placeholder="جستجوی کاربران...">
                </div>
                <div id="inviteSearchResults" class="invite-users-list">
                    <div class="alert info">برای جستجو، نام کاربری را تایپ کنید</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Notification Container -->
    <div id="notificationContainer"></div>
    
    <script src="assets/groups.js"></script>
    <?php include 'includes/webrtc_loader.php'; ?>
    <script style="display:none">
        let searchTimeout;
        
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

            loadMyGroups();
            loadJoinedGroups();
            loadGroupInvitations();
            
            // Event listeners
            setupEventListeners();
            
            // بروزرسانی منظم
            setInterval(loadGroupInvitations, 10000); // هر 10 ثانیه
            setInterval(updateActivity, 30000); // هر 30 ثانیه
        });
        
        function setupEventListeners() {
            // فرم ساخت گروه
            document.getElementById('createGroupForm').addEventListener('submit', function(e) {
                e.preventDefault();
                createGroup();
            });
            
            // جستجوی کاربران برای دعوت
            document.getElementById('inviteSearchInput').addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const query = this.value.trim();
                
                if (query.length < 2) {
                    document.getElementById('inviteSearchResults').innerHTML = 
                        '<div class="alert info">برای جستجو، حداقل 2 کاراکتر تایپ کنید</div>';
                    return;
                }
                
                searchTimeout = setTimeout(() => {
                    searchUsersForInvite(query);
                }, 500);
            });
        }
        
        // بارگذاری گروه‌های ساخته شده توسط کاربر
        function loadMyGroups() {
            fetch('api/get_my_groups.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayMyGroups(data.groups);
                } else {
                    document.getElementById('myGroups').innerHTML = 
                        '<div class="alert info">گروهی نساخته‌اید</div>';
                }
            })
            .catch(error => {
                console.error('خطا در بارگذاری گروه‌ها:', error);
            });
        }
        
        // بارگذاری گروه‌های عضو هستم
        function loadJoinedGroups() {
            fetch('api/get_joined_groups.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayJoinedGroups(data.groups);
                } else {
                    document.getElementById('joinedGroups').innerHTML = 
                        '<div class="alert info">عضو هیچ گروهی نیستید</div>';
                }
            })
            .catch(error => {
                console.error('خطا در بارگذاری گروه‌ها:', error);
            });
        }
        
        // بارگذاری دعوت‌نامه‌ها
        function loadGroupInvitations() {
            fetch('api/get_group_invitations.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayGroupInvitations(data.invitations);
                } else {
                    document.getElementById('groupInvitations').innerHTML = 
                        '<div class="alert info">دعوت‌نامه‌ای ندارید</div>';
                }
            })
            .catch(error => {
                console.error('خطا در بارگذاری دعوت‌نامه‌ها:', error);
            });
        }
        
        // نمایش گروه‌های من
        function displayMyGroups(groups) {
            const container = document.getElementById('myGroups');
            
            if (groups.length === 0) {
                container.innerHTML = '<div class="alert info">گروهی نساخته‌اید</div>';
                return;
            }
            
            let html = '';
            groups.forEach(group => {
                html += `
                    <div class="group-item">
                        <div class="group-info">
                            <h4>${group.name}</h4>
                            <p class="group-members">${group.member_count} عضو</p>
                        </div>
                        <div class="group-actions">
                            <button class="small-btn" onclick="openGroupChat(${group.id})">چت</button>
                            <button class="small-btn" onclick="showInviteUsersModal(${group.id})">دعوت</button>
                            <button class="small-btn delete-btn" onclick="deleteGroup(${group.id}, '${group.name}')">حذف</button>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        // نمایش گروه‌های عضو هستم
        function displayJoinedGroups(groups) {
            const container = document.getElementById('joinedGroups');
            
            if (groups.length === 0) {
                container.innerHTML = '<div class="alert info">عضو هیچ گروهی نیستید</div>';
                return;
            }
            
            let html = '';
            groups.forEach(group => {
                html += `
                    <div class="group-card" onclick="openGroupChat(${group.id})">
                        <div class="group-avatar">${group.name.charAt(0).toUpperCase()}</div>
                        <div class="group-details">
                            <h3>${group.name}</h3>
                            <p class="group-description">${group.description || 'بدون توضیحات'}</p>
                            <p class="group-stats">${group.member_count} عضو • ایجاد شده توسط ${group.creator_name}</p>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        // نمایش دعوت‌نامه‌ها
        function displayGroupInvitations(invitations) {
            const container = document.getElementById('groupInvitations');
            
            if (invitations.length === 0) {
                container.innerHTML = '<div class="alert info">دعوت‌نامه‌ای ندارید</div>';
                return;
            }
            
            let html = '';
            invitations.forEach(invitation => {
                html += `
                    <div class="invitation-item">
                        <div class="invitation-info">
                            <h5>${invitation.group_name}</h5>
                            <p>دعوت از ${invitation.creator_name}</p>
                        </div>
                        <div class="invitation-actions">
                            <button class="accept-btn" onclick="respondToInvitation(${invitation.group_id}, 'accept')">قبول</button>
                            <button class="reject-btn" onclick="respondToInvitation(${invitation.group_id}, 'reject')">رد</button>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        // نمایش modal ساخت گروه
        function showCreateGroupModal() {
            document.getElementById('createGroupModal').style.display = 'block';
        }
        
        // مخفی کردن modal ساخت گروه
        function hideCreateGroupModal() {
            document.getElementById('createGroupModal').style.display = 'none';
            document.getElementById('createGroupForm').reset();
        }
        
        // ساخت گروه جدید
        function createGroup() {
            const formData = new FormData(document.getElementById('createGroupForm'));
            
            fetch('api/create_group.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('گروه با موفقیت ساخته شد!');
                    hideCreateGroupModal();
                    loadMyGroups();
                    loadJoinedGroups();
                } else {
                    alert('خطا: ' + data.message);
                }
            })
            .catch(error => {
                console.error('خطا در ساخت گروه:', error);
                alert('خطا در ساخت گروه');
            });
        }
        
        // نمایش modal دعوت کاربران
        function showInviteUsersModal(groupId) {
            currentGroupId = groupId;
            document.getElementById('inviteUsersModal').style.display = 'block';
        }
        
        // مخفی کردن modal دعوت کاربران
        function hideInviteUsersModal() {
            document.getElementById('inviteUsersModal').style.display = 'none';
            document.getElementById('inviteSearchInput').value = '';
            document.getElementById('inviteSearchResults').innerHTML = 
                '<div class="alert info">برای جستجو، نام کاربری را تایپ کنید</div>';
        }
        
        // جستجوی کاربران برای دعوت
        function searchUsersForInvite(query) {
            fetch('api/search_users_for_invite.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `query=${encodeURIComponent(query)}&group_id=${currentGroupId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayUsersForInvite(data.users);
                } else {
                    document.getElementById('inviteSearchResults').innerHTML = 
                        '<div class="alert error">' + data.message + '</div>';
                }
            })
            .catch(error => {
                console.error('خطا در جستجو:', error);
            });
        }
        
        // نمایش کاربران برای دعوت
        function displayUsersForInvite(users) {
            const container = document.getElementById('inviteSearchResults');
            
            if (users.length === 0) {
                container.innerHTML = '<div class="alert info">کاربری یافت نشد</div>';
                return;
            }
            
            let html = '';
            users.forEach(user => {
                html += `
                    <div class="invite-user-item">
                        <div class="user-info">
                            <div class="user-avatar">${user.username.charAt(0).toUpperCase()}</div>
                            <span>${user.username}</span>
                        </div>
                        <button class="invite-btn" onclick="inviteUser(${user.id})">دعوت</button>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        // دعوت کاربر به گروه
        function inviteUser(userId) {
            fetch('api/invite_to_group.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `group_id=${currentGroupId}&user_id=${userId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('دعوت‌نامه ارسال شد!');
                    // بروزرسانی لیست
                    const query = document.getElementById('inviteSearchInput').value.trim();
                    if (query.length >= 2) {
                        searchUsersForInvite(query);
                    }
                } else {
                    alert('خطا: ' + data.message);
                }
            })
            .catch(error => {
                console.error('خطا در ارسال دعوت:', error);
                alert('خطا در ارسال دعوت');
            });
        }
        
        // پاسخ به دعوت‌نامه
        function respondToInvitation(groupId, response) {
            fetch('api/respond_to_invitation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `group_id=${groupId}&response=${response}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(response === 'accept' ? 'به گروه پیوستید!' : 'دعوت رد شد');
                    loadGroupInvitations();
                    if (response === 'accept') {
                        loadJoinedGroups();
                    }
                } else {
                    alert('خطا: ' + data.message);
                }
            })
            .catch(error => {
                console.error('خطا در پاسخ به دعوت:', error);
                alert('خطا در پاسخ به دعوت');
            });
        }
        
        // باز کردن چت گروهی
        function openGroupChat(groupId) {
            window.location.href = 'group_chat.php?group=' + groupId;
        }
        
        // حذف گروه
        async function deleteGroup(groupId, groupName) {
            if (!confirm(`آیا مطمئن هستید که می‌خواهید گروه "${groupName}" را حذف کنید؟\n\nاین عمل غیرقابل بازگشت است و تمام پیام‌ها و فایل‌های گروه حذف خواهند شد!`)) {
                return;
            }
            
            // تأیید مجدد
            if (!confirm('آیا واقعاً مطمئن هستید؟ این عمل قابل بازگشت نیست!')) {
                return;
            }
            
            try {
                const response = await fetch('api/delete_group.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `group_id=${groupId}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('گروه با موفقیت حذف شد', 'success');
                    // رفرش لیست گروه‌ها
                    loadMyGroups();
                    loadJoinedGroups();
                } else {
                    showNotification('خطا: ' + data.message, 'error');
                }
            } catch (error) {
                console.error('خطا در حذف گروه:', error);
                showNotification('خطا در حذف گروه', 'error');
            }
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
    <script src="assets/groups.js"></script>
    <?php include 'includes/webrtc_loader.php'; ?>
</body>
</html>