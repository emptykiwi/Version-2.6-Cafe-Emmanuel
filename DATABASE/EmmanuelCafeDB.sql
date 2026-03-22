-- ============================================================
-- COMBINED DATABASE BACKUP - CAFE EMMANUEL
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- ============================================================
-- DATABASE: addproduct
-- Source: EmmanuelCafeDB.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS `addproduct` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `addproduct`;

-- Table: cart
CREATE TABLE IF NOT EXISTS `cart` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fullname` varchar(255) NOT NULL,
  `contact` varchar(50) NOT NULL,
  `address` text NOT NULL,
  `cart` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`cart`)),
  `total` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) NOT NULL DEFAULT 'Pending',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `cart` (`id`, `fullname`, `contact`, `address`, `cart`, `total`, `created_at`, `status`) VALUES


-- Table: inquiries
CREATE TABLE IF NOT EXISTS `inquiries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'New',
  `received_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `inquiries` (`id`, `first_name`, `last_name`, `email`, `message`, `status`, `received_at`) VALUES


-- Table: orders
CREATE TABLE IF NOT EXISTS `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fullname` varchar(255) NOT NULL,
  `contact` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `payment_method` varchar(20) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Pending','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `orders` (`id`, `fullname`, `contact`, `address`, `payment_method`, `total`, `created_at`, `status`) VALUES


-- Table: products
CREATE TABLE IF NOT EXISTS `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `image` varchar(255) NOT NULL,
  `rating` int(11) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `category` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `products` (`id`, `name`, `price`, `image`, `rating`, `stock`, `category`) VALUES
(43, 'Nachos', 260.00, 'uploads/68f4c03d61c02_5D2F6E42-1049-4073-A645-FD39ADA21F99_4_5005_c.jpeg', 5, 10, 'dessert'),
(44, 'Kani Salad', 240.00, 'uploads/68f4c06db115e_9918BF56-DF46-469E-BCA9-BEBAEF1B31CE_4_5005_c.jpeg', 5, 12, 'pasta'),
(45, 'Carbonara', 260.00, 'uploads/68f4c0b4df829_4486ADD2-EACF-4E7D-80CC-D1913869ED82_1_105_c.jpeg', 5, 10, 'dessert'),
(46, 'Tonkatsu', 280.00, 'uploads/68f4c0eedf72e_93C2B5D1-EBB5-41AC-98E4-CF747DE7FE13_4_5005_c.jpeg', 5, 6, 'burger'),
(47, 'Hibiscus ', 160.00, 'uploads/68f4c21122bff_D324404D-5959-46DE-9D20-B9125ABCA8DC_4_5005_c.jpeg', 5, 5, 'coffee'),
(48, 'Brewed', 130.00, 'uploads/68f4c2d0a7cbe_menu-2.jpg', 5, 9, 'coffee'),
(49, 'Espresso', 120.00, 'uploads/68f4c30f15cb6_menu-4.jpg', 5, 1, 'coffee'),
(50, 'Matcha', 120.00, 'uploads/68f4dd7fe2cf6_menu-3.jpg', 5, 0, 'coffee');

-- Table: order_items
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: recently_deleted
CREATE TABLE IF NOT EXISTS `recently_deleted` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `contact` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `cart` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`cart`)),
  `total` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(50) NOT NULL,
  `deleted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `recently_deleted` (`id`, `order_id`, `fullname`, `contact`, `address`, `cart`, `total`, `created_at`, `status`, `deleted_at`) VALUES

CREATE TABLE IF NOT EXISTS `recently_deleted_products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `original_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL,
  `image` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `rating` int(11) DEFAULT 5,
  `deleted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `recently_deleted_products` (`id`, `original_id`, `name`, `price`, `stock`, `image`, `category`, `rating`, `deleted_at`) VALUES


-- Table: revenue
CREATE TABLE IF NOT EXISTS `revenue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `date_created` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `revenue` (`id`, `order_id`, `amount`, `date_created`) VALUES


-- ============================================================
-- DATABASE: login_system
-- Source: login_system.sql (Most complete version)
-- ============================================================

CREATE DATABASE IF NOT EXISTS `login_system` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `login_system`;

-- Table: users
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `role` enum('user','admin','super_admin') NOT NULL DEFAULT 'user',
  `profile_picture` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `username`, `password`, `fullname`, `email`, `role`, `created_at`) VALUES


-- Table: recently_deleted_users
CREATE TABLE IF NOT EXISTS `recently_deleted_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `original_id` int(11) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'user',
  `deleted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `recently_deleted_users` (`id`, `original_id`, `fullname`, `username`, `email`, `role`, `deleted_at`) VALUES


-- Table: notifications (From PHP code context)
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `order_id` INT,
    `type` VARCHAR(50) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `is_read` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_user_read` (`user_id`, `is_read`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: audit_log (From PHP code context)
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `action` VARCHAR(64) NOT NULL,
  `entity_type` VARCHAR(64) NULL,
  `entity_id` INT NULL,
  `details` JSON NULL,
  `ip` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (`user_id`),
  INDEX (`entity_type`),
  INDEX (`entity_id`),
  INDEX (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: otp_codes (From PHP code context)
CREATE TABLE IF NOT EXISTS `otp_codes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `code` VARCHAR(10) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL,
  `attempts` TINYINT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (`user_id`),
  INDEX (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: password_resets (From PHP code context)
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `reset_code` VARCHAR(10) NOT NULL,
  `reset_method` ENUM('email','phone') NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL,
  `attempts` TINYINT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (`user_id`),
  INDEX (`reset_code`),
  INDEX (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- DATABASE: connect_dashboard
-- Source: connect_dashboard.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS `connect_dashboard` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `connect_dashboard`;

CREATE TABLE IF NOT EXISTS `cart` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fullname` varchar(255) NOT NULL,
  `contact` varchar(50) NOT NULL,
  `address` text NOT NULL,
  `cart` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`cart`)),
  `total` decimal(10,2) NOT NULL,
  `payment_method` varchar(20) DEFAULT 'COD',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT 'Pending',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `cart` (`id`, `fullname`, `contact`, `address`, `cart`, `total`, `payment_method`, `created_at`, `status`) VALUES


CREATE TABLE IF NOT EXISTS `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fullname` varchar(100) NOT NULL,
  `contact` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `payment_method` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `order_date` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `orders` (`id`, `fullname`, `contact`, `address`, `total`, `payment_method`, `created_at`, `order_date`) VALUES


CREATE TABLE IF NOT EXISTS `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `connect_dashboard_items_fk` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `stockalert` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(100) DEFAULT NULL,
  `stock_remaining` int(11) DEFAULT NULL,
  `alert_date` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ============================================================
-- DATABASE: EmmanuelCafeDB
-- Source: products.sql, sales.sql, EmmanuelCafeDB.sql (Legacy)
-- ============================================================

CREATE DATABASE IF NOT EXISTS `EmmanuelCafeDB` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `EmmanuelCafeDB`;

CREATE TABLE IF NOT EXISTS `products` (
  `product_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_name` varchar(255) NOT NULL,
  `product_description` text DEFAULT NULL,
  `product_price` decimal(10,2) DEFAULT NULL,
  `product_stock` int(11) DEFAULT NULL,
  `product_image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `products` (`product_id`, `product_name`, `product_description`, `product_price`, `product_stock`, `product_image`) VALUES


CREATE TABLE IF NOT EXISTS `sales` (
  `sale_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) DEFAULT NULL,
  `quantity_sold` int(11) DEFAULT NULL,
  `sale_date` date DEFAULT NULL,
  PRIMARY KEY (`sale_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `sales` (`sale_id`, `product_id`, `quantity_sold`, `sale_date`) VALUES
(13, 1, 2, '2025-07-12');

CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(100) NOT NULL,
  `role` varchar(20) DEFAULT 'admin',
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;