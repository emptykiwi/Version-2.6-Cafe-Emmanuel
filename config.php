<?php
// config.php
// Gracefully handle database connections and table setup for Hostinger Live Server

// Turn off mysqli exceptions so we can handle errors ourselves
mysqli_report(MYSQLI_REPORT_OFF);

// --- LIVE DATABASE CREDENTIALS ---
$host = 'localhost';
$user = 'u763865560_Mancave';
$pass = 'ManCave2025';
$db   = 'u763865560_EmmanuelCafeDB'; // All tables (users, products, orders) are here

// 1) Connect to the application database
$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    // Fallback: Output error safely
    die('Database connection failed: ' . mysqli_connect_error());
}

// Set charset
mysqli_set_charset($conn, 'utf8mb4');

// 2) Ensure `users` table exists
$createUsersSql = "CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `fullname` VARCHAR(100) DEFAULT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `contact` VARCHAR(20) DEFAULT NULL,
  `gender` VARCHAR(20) DEFAULT NULL,
  `role` ENUM('user','admin','super_admin') NOT NULL DEFAULT 'user',
  `profile_picture` VARCHAR(255) DEFAULT NULL,
  `is_verified` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
mysqli_query($conn, $createUsersSql);

// 2b) Database Patches: Add missing columns if they don't exist
$columnsToCheck = [
    'profile_picture' => "VARCHAR(255) NULL AFTER `role`",
    'contact'         => "VARCHAR(20) NULL AFTER `email`",
    'gender'          => "VARCHAR(20) NULL AFTER `contact`",
    'is_verified'     => "TINYINT(1) DEFAULT 0 AFTER `gender`"
];

foreach ($columnsToCheck as $col => $def) {
    $chk = $conn->query("SHOW COLUMNS FROM `users` LIKE '$col'");
    if ($chk && $chk->num_rows == 0) {
        @$conn->query("ALTER TABLE `users` ADD COLUMN `$col` $def");
    }
}

// 2c) Ensure Audit Log Table Exists
$createAuditSql = "CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT NOT NULL,
  `admin_name` VARCHAR(255) NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `description` TEXT NOT NULL,
  `table_name` VARCHAR(50),
  `record_id` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_admin_id (admin_id),
  INDEX idx_action (action),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
mysqli_query($conn, $createAuditSql);

// 2d) Ensure OTP Codes Table Exists
$createOtpSql = "CREATE TABLE IF NOT EXISTS `otp_codes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `code` VARCHAR(10) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL,
  `attempts` TINYINT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
mysqli_query($conn, $createOtpSql);

// 2e) Ensure Password Resets Table Exists
$createPasswordResetsSql = "CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `reset_code` VARCHAR(10) NOT NULL,
  `reset_method` ENUM('email','phone') NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL,
  `attempts` TINYINT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
mysqli_query($conn, $createPasswordResetsSql);

// 3) PRODUCT & ORDER TABLES SETUP
// We use $conn because on live server, these tables are in the SAME database as users.
$ap = $conn; // Alias $ap to $conn for compatibility

if ($ap) {
    // Ensure `orders` has user_id
    $chk = $ap->query("SHOW COLUMNS FROM `orders` LIKE 'user_id'");
    if ($chk && $chk->num_rows == 0) {
        @$ap->query("ALTER TABLE `orders` ADD COLUMN `user_id` INT NULL AFTER `id`");
        @$ap->query("CREATE INDEX idx_orders_user_id ON `orders` (`user_id`)");
    }

    // Ensure `cart` has necessary columns
    $cartCols = [
        'user_id'       => "INT NULL AFTER `id`",
        'cancel_reason' => "VARCHAR(255) NULL AFTER `status`",
        'cancelled_at'  => "TIMESTAMP NULL AFTER `cancel_reason`"
    ];
    
    foreach ($cartCols as $col => $def) {
        $chk = $ap->query("SHOW COLUMNS FROM `cart` LIKE '$col'");
        if ($chk && $chk->num_rows == 0) {
            @$ap->query("ALTER TABLE `cart` ADD COLUMN `$col` $def");
        }
    }
}

// OTP feature toggles
if (!defined('OTP_ENABLED')) define('OTP_ENABLED', true);
if (!defined('OTP_REQUIRE_FOR_ADMINS')) define('OTP_REQUIRE_FOR_ADMINS', true);