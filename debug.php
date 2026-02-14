<?php
/**
 * فایل دیباگ برای تست عملکرد سیستم
 */

// فعال کردن نمایش خطاها
error_reporting(E_ALL);
ini_set('display_errors', 1);

// چک نصب
if (!file_exists('installed.lock')) {
    die('سیستم نصب نشده است. <a href="install.php">نصب کنید</a>');
}

session_start();

// چک لاگین
if (!isset($_SESSION['user_id'])) {
    die('لطفاً وارد شوید. <a href="login.php">ورود</a>');
}

require_once 'config/db.php';

echo "<h1>🔍 دیباگ سیستم وب‌چت</h1>";
echo "<p>کاربر جاری: " . htmlspecialchars($_SESSION['username']) . " (ID: " . $_SESSION['user_id'] . ")</p>";

// تست API ها
echo "<h2>تست API ها</h2>";

// تست search_users
echo "<h3>تست جستجوی کاربران</h3>";
try {
    $stmt = $pdo->prepare("
        SELECT 
            u.id, 
            u.username, 
            u.last_seen,
            CASE 
                WHEN u.last_seen >= DATE_SUB(NOW(), INTERVAL 2 MINUTE) THEN 1 
                ELSE 0 
            END as is_online
        FROM users u
        WHERE u.username LIKE ? 
        AND u.id != ? 
        AND u.id NOT IN (
            SELECT blocked_id FROM user_blocks WHERE blocker_id = ?
        )
        AND u.id NOT IN (
            SELECT blocker_id FROM user_blocks WHERE blocked_id = ?
        )
        ORDER BY is_online DESC, u.username ASC 
        LIMIT 5
    ");
    
    $searchTerm = '%' . ($_SESSION['username'][0] ?? 'a') . '%';
    $stmt->execute([$searchTerm, $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✅ جستجوی کاربران کار می‌کند. تعداد یافت شده: " . count($users) . "<br>";
    foreach ($users as $user) {
        echo "&nbsp;&nbsp;- " . htmlspecialchars($user['username']) . " (" . ($user['is_online'] ? 'آنلاین' : 'آفلاین') . ")<br>";
    }
} catch (Exception $e) {
    echo "❌ خطا در جستجوی کاربران: " . $e->getMessage() . "<br>";
}

// تست ارسال پیام
echo "<h3>تست ارسال پیام</h3>";
if (isset($_POST['test_message']) && isset($_POST['target_user'])) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$_POST['target_user'], $_SESSION['user_id']]);
        $target = $stmt->fetch();
        
        if ($target) {
            $stmt = $pdo->prepare("
                INSERT INTO messages (sender_id, receiver_id, message, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$_SESSION['user_id'], $target['id'], $_POST['test_message']]);
            
            echo "✅ پیام تست با موفقیت ارسال شد!<br>";
        } else {
            echo "❌ کاربر مقصد یافت نشد<br>";
        }
    } catch (Exception $e) {
        echo "❌ خطا در ارسال پیام: " . $e->getMessage() . "<br>";
    }
}

// فرم تست ارسال پیام
echo '<form method="POST">';
echo '<input type="text" name="target_user" placeholder="نام کاربری مقصد" required>';
echo '<input type="text" name="test_message" placeholder="پیام تست" value="سلام، این یک پیام تست است" required>';
echo '<button type="submit">ارسال پیام تست</button>';
echo '</form>';

// تست دریافت پیام‌ها
echo "<h3>تست دریافت پیام‌ها</h3>";
try {
    $stmt = $pdo->prepare("
        SELECT 
            m.id,
            m.message,
            m.created_at,
            u1.username as sender_name,
            u2.username as receiver_name
        FROM messages m
        INNER JOIN users u1 ON m.sender_id = u1.id
        INNER JOIN users u2 ON m.receiver_id = u2.id
        WHERE m.sender_id = ? OR m.receiver_id = ?
        ORDER BY m.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✅ دریافت پیام‌ها کار می‌کند. تعداد پیام‌ها: " . count($messages) . "<br>";
    foreach ($messages as $msg) {
        echo "&nbsp;&nbsp;- از " . htmlspecialchars($msg['sender_name']) . " به " . htmlspecialchars($msg['receiver_name']) . ": " . htmlspecialchars(substr($msg['message'], 0, 50)) . "...<br>";
    }
} catch (Exception $e) {
    echo "❌ خطا در دریافت پیام‌ها: " . $e->getMessage() . "<br>";
}

// تست جداول
echo "<h2>وضعیت جداول</h2>";
$tables = [
    'users' => 'کاربران',
    'messages' => 'پیام‌ها',
    'webrtc_signals' => 'سیگنال‌های WebRTC',
    'chat_files' => 'فایل‌های چت',
    'user_blocks' => 'بلاک‌ها',
    'recent_chats' => 'چت‌های اخیر',
    'groups_table' => 'گروه‌ها',
    'group_members' => 'اعضای گروه',
    'group_messages' => 'پیام‌های گروهی',
    'group_files' => 'فایل‌های گروهی'
];

foreach ($tables as $table => $name) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $count = $stmt->fetch()['count'];
        echo "✅ $name ($table): $count رکورد<br>";
    } catch (Exception $e) {
        echo "❌ $name ($table): خطا - " . $e->getMessage() . "<br>";
    }
}

// تست فایل‌ها
echo "<h2>تست فایل‌ها و پوشه‌ها</h2>";
$files = [
    'api/send_message.php' => 'API ارسال پیام',
    'api/get_messages.php' => 'API دریافت پیام‌ها',
    'api/search_users.php' => 'API جستجوی کاربران',
    'api/send_signal.php' => 'API ارسال سیگنال WebRTC',
    'api/get_signal.php' => 'API دریافت سیگنال WebRTC',
    'assets/chat.js' => 'JavaScript چت',
    'assets/webrtc.js' => 'JavaScript WebRTC',
    'uploads/chat_files/' => 'پوشه فایل‌ها'
];

foreach ($files as $file => $name) {
    if (file_exists($file)) {
        if (is_dir($file)) {
            echo "✅ $name: موجود و " . (is_writable($file) ? 'قابل نوشتن' : 'غیرقابل نوشتن') . "<br>";
        } else {
            echo "✅ $name: موجود (" . number_format(filesize($file)) . " بایت)<br>";
        }
    } else {
        echo "❌ $name: موجود نیست<br>";
    }
}

// تست تنظیمات PHP
echo "<h2>تنظیمات PHP</h2>";
echo "- حداکثر حجم آپلود: " . ini_get('upload_max_filesize') . "<br>";
echo "- حداکثر حجم POST: " . ini_get('post_max_size') . "<br>";
echo "- حداکثر زمان اجرا: " . ini_get('max_execution_time') . " ثانیه<br>";
echo "- حافظه: " . ini_get('memory_limit') . "<br>";

echo "<hr>";
echo "<p><a href='dashboard.php'>بازگشت به داشبورد</a> | <a href='test.php'>تست کامل</a></p>";
?>