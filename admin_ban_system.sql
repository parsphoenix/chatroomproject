-- جدول ممنوعیت کاربران
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
    INDEX idx_active_bans (is_active),
    INDEX idx_browser_fingerprint (browser_fingerprint(100))
);

-- اضافه کردن فیلد نقش کاربر به جدول users
ALTER TABLE users ADD COLUMN IF NOT EXISTS user_role ENUM('user', 'admin') DEFAULT 'user';

-- تنظیم کاربر admin به عنوان ادمین
UPDATE users SET user_role = 'admin' WHERE username = 'admin';

-- جدول لاگ فعالیت‌های ادمین
CREATE TABLE IF NOT EXISTS admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action_type ENUM('ban_user', 'unban_user', 'view_users') NOT NULL,
    target_user_id INT,
    action_details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_admin_logs (admin_id, created_at)
);