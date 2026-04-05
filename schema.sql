CREATE DATABASE IF NOT EXISTS `karta_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `karta_db`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `direct_sales`;
DROP TABLE IF EXISTS `visit_products`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `formulas`;
DROP TABLE IF EXISTS `visits`;
DROP TABLE IF EXISTS `materials`;
DROP TABLE IF EXISTS `clients`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `clients` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `allergy_note` VARCHAR(255) DEFAULT NULL,
  `client_tags` VARCHAR(255) DEFAULT NULL,
  `is_favorite` TINYINT(1) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `materials` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `brand` VARCHAR(50) NOT NULL DEFAULT 'L''Oréal',
  `category` VARCHAR(50) NOT NULL,
  `name` VARCHAR(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS `visits` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `client_id` INT NOT NULL,
  `visit_date` DATE NOT NULL,
  `hair_texture` VARCHAR(50) DEFAULT NULL,
  `hair_condition` VARCHAR(100) DEFAULT NULL,
  `note` TEXT,
  `price` INT DEFAULT 0,
  `s_metal_detox` TINYINT(1) DEFAULT 0,
  `s_trim` TINYINT(1) DEFAULT 0,
  `s_blow` TINYINT(1) DEFAULT 0,
  `s_curl` TINYINT(1) DEFAULT 0,
  `s_iron` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `formulas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `visit_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `amount_g` int(11) NOT NULL,
  `bowl_name` varchar(100) DEFAULT 'Miska 1',
  PRIMARY KEY (`id`),
  FOREIGN KEY (`visit_id`) REFERENCES `visits`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`material_id`) REFERENCES `materials`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `materials` (`category`, `name`) VALUES
('Majirel', '1.0 Černá'),
('Majirel', '7.0 Střední blond'),
('Majirel', '10.1 Platinová'),
('Inoa', '4.15 Kaštanová'),
('Oxydant', '3% (10 Vol.)'),
('Oxydant', '6% (20 Vol.)'),
('Oxydant', '9% (30 Vol.)');

CREATE TABLE IF NOT EXISTS `products` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `brand` VARCHAR(50) NOT NULL DEFAULT 'Ostatní',
  `name` VARCHAR(100) NOT NULL,
  `price` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1
);

CREATE TABLE IF NOT EXISTS `visit_products` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `visit_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `price_sold` INT NOT NULL,
  `amount` INT DEFAULT 1,
  FOREIGN KEY (`visit_id`) REFERENCES `visits`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `direct_sales` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `product_id` INT NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `unit_price` INT NOT NULL DEFAULT 0,
  `sold_at` DATE NOT NULL,
  `note` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
