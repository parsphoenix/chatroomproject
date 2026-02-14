<?php
/**
 * صفحه مدیریت کاربران بلاک شده
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

// دریافت لیست کاربران بلاک شده
try {
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.username,
            u.last_seen,
            ub.created_at as blocked_at
        FROM user_blocks ub
        INNER JOIN users u ON ub.blocked_id = u.id
        WHERE ub.blocker_id = ?
        ORDER BY ub.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $blocked_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die(__('error_load_blocked') . ': ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html dir="<?= get_direction() ?>" lang="<?= get_lang_code() ?>" data-theme="<?= $user_theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('blocked_users_title') ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/chat-fixes.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?= __('blocked_users') ?></h1>
            <a href="dashboard.php" class="btn btn-secondary"><?= __('back_to_dashboard') ?></a>
        </div>
        
        <?php require_once 'includes/webrtc_loader.php'; ?>
        
        <div class="content">
            <?php if (empty($blocked_users)): ?>
                <div class="alert info">
                    <?= __('no_blocked_users') ?>
                </div>
            <?php else: ?>
                <div class="blocked-users-list">
                    <?php foreach ($blocked_users as $user): ?>
                        <div class="user-item blocked-user" data-username="<?= htmlspecialchars($user['username']) ?>">
                            <div class="user-avatar">
                                <?= strtoupper(substr($user['username'], 0, 1)) ?>
                            </div>
                            <div class="user-info">
                                <div class="user-name"><?= htmlspecialchars($user['username']) ?></div>
                                <div class="user-details">
                                    <span><?= __('blocked_at', ['date' => date('Y/m/d H:i', strtotime($user['blocked_at']))]) ?></span>
                                </div>
                            </div>
                            <div class="user-actions">
                                <button class="btn btn-success" onclick="unblockUser('<?= htmlspecialchars($user['username']) ?>')">
                                    <?= __('unblock_btn') ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Notification Container -->
    <div id="notificationContainer"></div>
    
    <script>
        // آنبلاک کردن کاربر
        async function unblockUser(username) {
            if (!confirm('<?= __('unblock_confirm') ?>')) return;
            
            try {
                const response = await fetch('api/unblock_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `username=${encodeURIComponent(username)}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('کاربر با موفقیت آنبلاک شد', 'success');
                    
                    // حذف کاربر از لیست
                    const userItem = document.querySelector(`[data-username="${username}"]`);
                    if (userItem) {
                        userItem.style.opacity = '0';
                        userItem.style.transform = 'translateX(-100%)';
                        setTimeout(() => userItem.remove(), 300);
                    }
                    
                    // چک کردن اینکه آیا لیست خالی شده
                    setTimeout(() => {
                        const remainingUsers = document.querySelectorAll('.blocked-user');
                        if (remainingUsers.length === 0) {
                            location.reload();
                        }
                    }, 500);
                    
                } else {
                    showNotification('خطا: ' + data.message, 'error');
                }
            } catch (error) {
                console.error('خطا در آنبلاک کردن:', error);
                showNotification('خطا در آنبلاک کردن کاربر', 'error');
            }
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
    
    <style>
        .blocked-users-list {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .blocked-user {
            transition: all 0.3s ease;
        }
        
        .blocked-user:hover {
            transform: translateX(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .user-item {
            display: flex;
            align-items: center;
            padding: 15px;
            margin-bottom: 10px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid #e0e0e0;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
            margin-left: 15px;
        }
        
        .user-info {
            flex: 1;
        }
        
        .user-name {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .user-details {
            color: #666;
            font-size: 14px;
        }
        
        .user-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        
        .btn-success {
            background: #38a169;
            color: white;
        }
        
        .btn-secondary {
            background: #718096;
            color: white;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px 0;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
    </style>
</body>
</html>
