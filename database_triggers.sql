-- Triggers برای بروزرسانی خودکار recent_chats

DELIMITER $$

-- Trigger برای بروزرسانی recent_chats هنگام ارسال پیام جدید
CREATE TRIGGER update_recent_chats_after_message
AFTER INSERT ON messages
FOR EACH ROW
BEGIN
    -- بروزرسانی یا ایجاد رکورد برای فرستنده
    INSERT INTO recent_chats (user_id, chat_with_id, last_message_id, unread_count, updated_at)
    VALUES (NEW.sender_id, NEW.receiver_id, NEW.id, 0, NOW())
    ON DUPLICATE KEY UPDATE
        last_message_id = NEW.id,
        updated_at = NOW();
    
    -- بروزرسانی یا ایجاد رکورد برای گیرنده (با افزایش unread_count)
    INSERT INTO recent_chats (user_id, chat_with_id, last_message_id, unread_count, updated_at)
    VALUES (NEW.receiver_id, NEW.sender_id, NEW.id, 1, NOW())
    ON DUPLICATE KEY UPDATE
        last_message_id = NEW.id,
        unread_count = unread_count + 1,
        updated_at = NOW();
END$$

-- Trigger برای کاهش unread_count هنگام خواندن پیام‌ها
CREATE TRIGGER update_unread_count_after_read
AFTER UPDATE ON messages
FOR EACH ROW
BEGIN
    IF OLD.is_read = FALSE AND NEW.is_read = TRUE THEN
        UPDATE recent_chats 
        SET unread_count = GREATEST(0, unread_count - 1)
        WHERE user_id = NEW.receiver_id AND chat_with_id = NEW.sender_id;
    END IF;
END$$

-- Trigger برای حذف recent_chats هنگام حذف پیام‌ها
CREATE TRIGGER cleanup_recent_chats_after_message_delete
AFTER DELETE ON messages
FOR EACH ROW
BEGIN
    -- چک کردن اینکه آیا پیام‌های دیگری بین این دو کاربر وجود دارد
    DECLARE msg_count INT DEFAULT 0;
    
    SELECT COUNT(*) INTO msg_count
    FROM messages 
    WHERE (sender_id = OLD.sender_id AND receiver_id = OLD.receiver_id)
       OR (sender_id = OLD.receiver_id AND receiver_id = OLD.sender_id);
    
    -- اگر پیامی نمانده، recent_chats را حذف کن
    IF msg_count = 0 THEN
        DELETE FROM recent_chats 
        WHERE (user_id = OLD.sender_id AND chat_with_id = OLD.receiver_id)
           OR (user_id = OLD.receiver_id AND chat_with_id = OLD.sender_id);
    ELSE
        -- در غیر این صورت، آخرین پیام را پیدا کن و بروزرسانی کن
        DECLARE last_msg_id INT DEFAULT NULL;
        
        SELECT id INTO last_msg_id
        FROM messages 
        WHERE (sender_id = OLD.sender_id AND receiver_id = OLD.receiver_id)
           OR (sender_id = OLD.receiver_id AND receiver_id = OLD.sender_id)
        ORDER BY created_at DESC, id DESC
        LIMIT 1;
        
        UPDATE recent_chats 
        SET last_message_id = last_msg_id
        WHERE (user_id = OLD.sender_id AND chat_with_id = OLD.receiver_id)
           OR (user_id = OLD.receiver_id AND chat_with_id = OLD.sender_id);
    END IF;
END$$

DELIMITER ;

-- Index های اضافی برای بهبود عملکرد
CREATE INDEX idx_messages_read ON messages(receiver_id, sender_id, is_read);
CREATE INDEX idx_recent_chats_updated ON recent_chats(user_id, updated_at DESC);