# 💬 Simple and Practical WebChat

A complete chat system with audio and video calling capabilities that works on cPanel without needing Node.js.

## ✨ Features

- 🚀 **Auto-Installation**: Fully automated installation system with a graphical interface.
- 🔐 **Secure Authentication**: Registration and login with password hashing.
- 🔍 **User Search**: Fast and easy user search + public users list.
- 💬 **Advanced Text Chat**: Send and receive text messages in real-time.
- 📎 **File Sharing**: Upload files up to 5MB (max 6 files per chat).
- 🗑️ **Delete Messages**: Selectively or completely delete messages (for yourself or both sides).
- 📹 **Video/Audio Calls**: Advanced WebRTC with device detection.
- 🚫 **Block Users**: Ability to block unwanted users.
- 📊 **Recent Chats Sidebar**: View latest conversations and unread messages.
- 🌍 **Public Mode**: Option to appear in the public users list.
- 🎨 **Beautiful Background**: Telegram-like design for the chat page.
- 📱 **Responsive**: Compatible with mobile and desktop.
- 🌐 **Multi-language**: Full support for English and Persian (RTL/LTR).

## 🛠 Technologies Used

- **Frontend**: HTML5, CSS3, Pure JavaScript
- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Real-time**: AJAX Long Polling
- **Calling**: WebRTC P2P

## 📋 Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Modern browser with WebRTC support
- HTTPS for audio/video calling (optional but recommended)

## 🚀 Installation and Setup

### Step 1: Upload Files

1. Upload all project files to your site's main directory (public_html).
2. Ensure the file structure is as follows:

```
/
├── install.php
├── index.php
├── login.php
├── register.php
├── logout.php
├── dashboard.php
├── chat.php
├── /api/
│   ├── search_users.php
│   ├── send_message.php
│   ├── get_messages.php
│   ├── update_activity.php
│   ├── send_signal.php
│   └── get_signal.php
├── /assets/
│   ├── style.css
│   ├── install.css
│   ├── chat.js
│   └── webrtc.js
├── /config/
│   └── db.sample.php
├── /includes/
│   └── lang_helper.php
├── /lang/
│   ├── fa.php
│   └── en.php
└── README.md
```

### Step 2: Run Auto-Installation

1. Open your site in a browser.
2. You will be automatically redirected to the installation page.
3. Select your preferred language (English or Persian).
4. Enter your database information:
   - **Database Host**: Usually `localhost`
   - **Database Name**: Your database name
   - **Database Username**: Your database username
   - **Database Password**: Your database password
   - **Database Port**: Usually `3306`

5. Define the initial admin account:
   - **Admin Username**: Admin username (3-50 characters)
   - **Admin Password**: Admin password (at least 6 characters)

6. Click "Start Installation".

### Step 3: Complete Installation

After successful installation:
- `installed.lock` file is created.
- `config/db.php` file is created with database information.
- Database tables are automatically created.
- Initial admin account is created.

## 📊 Database Structure

### Users Table (users)
```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_public TINYINT(1) DEFAULT 1,
    last_seen DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Messages Table (messages)
```sql
CREATE TABLE messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT,
    file_path VARCHAR(255),
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```
