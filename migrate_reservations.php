<?php
require_once 'db_connect.php';

echo "Checking 'reservations' table schema...\n";

// 1. Ensure `reservations` table exists with core columns
$createReservationsSql = "CREATE TABLE IF NOT EXISTS `reservations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `res_name` VARCHAR(255) NOT NULL,
  `res_email` VARCHAR(255) NOT NULL,
  `res_phone` VARCHAR(50) NOT NULL,
  `res_date` DATE NOT NULL,
  `res_time` TIME NOT NULL,
  `res_guests` INT NOT NULL DEFAULT 1,
  `res_notes` TEXT,
  `status` ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'pending',
  `valid_id` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

if ($conn->query($createReservationsSql)) {
    echo "Table 'reservations' is ready.\n";
} else {
    echo "Error creating/checking table: " . $conn->error . "\n";
}

// 2. Add `valid_id` column if it's missing (in case the table already existed)
$chk = $conn->query("SHOW COLUMNS FROM `reservations` LIKE 'valid_id'");
if ($chk && $chk->num_rows == 0) {
    if ($conn->query("ALTER TABLE `reservations` ADD COLUMN `valid_id` VARCHAR(255) DEFAULT NULL AFTER `res_notes`")) {
        echo "Column 'valid_id' added successfully.\n";
    } else {
        echo "Error adding 'valid_id' column: " . $conn->error . "\n";
    }
} else {
    echo "Column 'valid_id' already exists.\n";
}

// 3. Create uploads directory
$upload_dir = 'uploads/reservations';
if (!is_dir($upload_dir)) {
    if (mkdir($upload_dir, 0777, true)) {
        echo "Upload directory '$upload_dir' created.\n";
    } else {
        echo "Failed to create upload directory.\n";
    }
} else {
    echo "Upload directory already exists.\n";
}

echo "Database enhancement complete.\n";
?>