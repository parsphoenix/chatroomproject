<?php
/**
 * Middleware برای چک ممنوعیت کاربران
 */

function checkUserBan($pdo, $user_id) {
    try {
        // چک ممنوعیت کاربر
        $stmt = $pdo->prepare("
            SELECT 
                ub.ban_reason,
                ub.banned_at,
                admin.username as banned_by_admin
            FROM user_bans ub
            INNER JOIN users admin ON ub.banned_by_admin_id = admin.id
            WHERE ub.banned_user_id = ? AND ub.is_active = TRUE
        ");
        $stmt->execute([$user_id]);
        $ban = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($ban) {
            // کاربر ممنوع است
            session_destroy();
            
            // نمایش صفحه ممنوعیت
            showBanPage($ban);
            exit;
        }
        
        // چک ممنوعیت بر اساس مرورگر/IP
        $browser_fingerprint = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip_address = $_SERVER['HTTP_X_REAL_IP'];
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                ub.ban_reason,
                ub.banned_at,
                admin.username as banned_by_admin,
                u.username as banned_username
            FROM user_bans ub
            INNER JOIN users admin ON ub.banned_by_admin_id = admin.id
            INNER JOIN users u ON ub.banned_user_id = u.id
            WHERE ub.is_active = TRUE 
            AND (ub.browser_fingerprint = ? OR ub.ip_address = ?)
            LIMIT 1
        ");
        $stmt->execute([$browser_fingerprint, $ip_address]);
        $device_ban = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($device_ban) {
            // دستگاه/IP ممنوع است
            session_destroy();
            showBanPage($device_ban, true);
            exit;
        }
        
    } catch (PDOException $e) {
        // در صورت خطا، اجازه ادامه بده
        error_log('خطا در چک ممنوعیت: ' . $e->getMessage());
    }
}

function showBanPage($ban, $is_device_ban = false) {
    ?>
    <!DOCTYPE html>
    <html dir="rtl" lang="fa">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>دسترسی ممنوع - وب‌چت</title>
        <link rel="stylesheet" href="assets/fonts.css">
        <link rel="stylesheet" href="assets/style.css">
        <style>
            * {
                font-family: 'Vazir', 'Tahoma', sans-serif !important;
            }
            .ban-container {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                background: linear-gradient(135deg, #e74c3c, #c0392b);
            }
            
            .ban-card {
                background: white;
                border-radius: 20px;
                padding: 40px;
                max-width: 600px;
                width: 100%;
                text-align: center;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            }
            
            .ban-icon {
                font-size: 80px;
                color: #e74c3c;
                margin-bottom: 20px;
            }
            
            .ban-title {
                font-size: 2.5em;
                color: #e74c3c;
                margin-bottom: 20px;
                font-weight: bold;
            }
            
            .ban-message {
                font-size: 1.2em;
                color: #666;
                margin-bottom: 30px;
                line-height: 1.6;
            }
            
            .ban-details {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 10px;
                margin: 20px 0;
                text-align: right;
            }
            
            .ban-detail-item {
                margin-bottom: 10px;
                padding: 5px 0;
                border-bottom: 1px solid #eee;
            }
            
            .ban-detail-label {
                font-weight: bold;
                color: #333;
            }
            
            .ban-detail-value {
                color: #666;
                margin-right: 10px;
            }
            
            .contact-info {
                background: #e3f2fd;
                padding: 20px;
                border-radius: 10px;
                margin-top: 30px;
            }
            
            .contact-title {
                font-weight: bold;
                color: #1976d2;
                margin-bottom: 10px;
            }
            
            .contact-text {
                color: #666;
                line-height: 1.6;
            }
        </style>
    </head>
    <body>
        <div class="ban-container">
            <div class="ban-card">
                <div class="ban-icon">🚫</div>
                <h1 class="ban-title">دسترسی ممنوع</h1>
                
                <?php if ($is_device_ban): ?>
                    <div class="ban-message">
                        دستگاه یا شبکه شما به دلیل ممنوعیت کاربر <strong><?= htmlspecialchars($ban['banned_username']) ?></strong> 
                        از دسترسی به این سایت محروم شده است.
                    </div>
                <?php else: ?>
                    <div class="ban-message">
                        حساب کاربری شما توسط مدیریت سایت مسدود شده است.
                    </div>
                <?php endif; ?>
                
                <div class="ban-details">
                    <div class="ban-detail-item">
                        <span class="ban-detail-label">دلیل ممنوعیت:</span>
                        <span class="ban-detail-value"><?= htmlspecialchars($ban['ban_reason'] ?: 'مشخص نشده') ?></span>
                    </div>
                    <div class="ban-detail-item">
                        <span class="ban-detail-label">تاریخ ممنوعیت:</span>
                        <span class="ban-detail-value"><?= date('Y/m/d H:i', strtotime($ban['banned_at'])) ?></span>
                    </div>
                    <div class="ban-detail-item">
                        <span class="ban-detail-label">مسدود شده توسط:</span>
                        <span class="ban-detail-value"><?= htmlspecialchars($ban['banned_by_admin']) ?></span>
                    </div>
                </div>
                
                <div class="contact-info">
                    <div class="contact-title">📞 تماس با پشتیبانی</div>
                    <div class="contact-text">
                        در صورتی که فکر می‌کنید این ممنوعیت اشتباه است، می‌توانید با مدیریت سایت تماس بگیرید.
                        <br><br>
                        <strong>نکته:</strong> تلاش برای دور زدن این ممنوعیت ممکن است منجر به ممنوعیت دائمی شود.
                    </div>
                </div>
                
                <div style="margin-top: 30px;">
                    <a href="index.php" style="color: #007bff; text-decoration: none; font-weight: bold;">
                        🏠 بازگشت به صفحه اصلی
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>