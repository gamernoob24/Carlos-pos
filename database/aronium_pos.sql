-- =============================================================
--  Aronium POS Web  —  database schema + starter data
--  MySQL 5.7+ / MariaDB 10.2+   (charset: utf8mb4)
--  Import: phpMyAdmin -> Import  |  or  mysql -u root -p < aronium_pos.sql
--  Safe to re-run: uses CREATE TABLE IF NOT EXISTS / INSERT IGNORE
-- =============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `aronium_pos`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `aronium_pos`;

-- -------------------------------------------------------------
-- Users (cashiers & administrators)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(100) NOT NULL,
  `username`      VARCHAR(50)  NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role`          ENUM('admin','cashier') NOT NULL DEFAULT 'cashier',
  `active`        TINYINT(1)   NOT NULL DEFAULT 1,
  `last_login`    DATETIME NULL DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Product categories
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Products / stock
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sku`           VARCHAR(64)  NOT NULL,
  `barcode`       VARCHAR(64)  NULL DEFAULT NULL,
  `name`          VARCHAR(200) NOT NULL,
  `category_id`   INT UNSIGNED NULL DEFAULT NULL,
  `cost_price`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `selling_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `stock_qty`     DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  `reorder_level` DECIMAL(12,3) NOT NULL DEFAULT 5.000,
  `unit`          VARCHAR(20)  NOT NULL DEFAULT 'pc',
  `tax_exempt`    TINYINT(1)   NOT NULL DEFAULT 0,
  `active`        TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_sku` (`sku`),
  UNIQUE KEY `uq_products_barcode` (`barcode`),
  KEY `ix_products_cat` (`category_id`),
  KEY `ix_products_name` (`name`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`)
    REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Sales (completed / voided transactions)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sales` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sale_no`        VARCHAR(32)  NOT NULL,
  `user_id`        INT UNSIGNED NULL DEFAULT NULL,
  `user_name`      VARCHAR(100) NOT NULL DEFAULT '',
  `customer_name`  VARCHAR(150) NULL DEFAULT NULL,
  `subtotal`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `tax_total`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total`          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `paid_amount`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `change_amount`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `payment_method` ENUM('cash','card','ewallet') NOT NULL DEFAULT 'cash',
  `status`         ENUM('completed','voided') NOT NULL DEFAULT 'completed',
  `note`           VARCHAR(255) NULL DEFAULT NULL,
  `voided_at`      DATETIME NULL DEFAULT NULL,
  `voided_by`      VARCHAR(100) NULL DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sales_no` (`sale_no`),
  KEY `ix_sales_created` (`created_at`),
  KEY `ix_sales_status` (`status`),
  CONSTRAINT `fk_sales_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Sale line items (snapshot of product data at time of sale)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sale_items` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sale_id`         INT UNSIGNED NOT NULL,
  `product_id`      INT UNSIGNED NULL DEFAULT NULL,
  `product_name`    VARCHAR(200) NOT NULL,
  `sku`             VARCHAR(64)  NOT NULL DEFAULT '',
  `qty`             DECIMAL(12,3) NOT NULL DEFAULT 1.000,
  `unit_price`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `tax_rate`        DECIMAL(6,3)  NOT NULL DEFAULT 0.000,
  `tax_amount`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `line_total`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_items_sale` (`sale_id`),
  KEY `ix_items_product` (`product_id`),
  CONSTRAINT `fk_items_sale` FOREIGN KEY (`sale_id`)
    REFERENCES `sales` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_items_product` FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Stock movements (audit trail for every stock change)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_movements` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`    INT UNSIGNED NULL DEFAULT NULL,
  `qty_change`    DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  `balance_after` DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  `reason`        VARCHAR(150) NOT NULL DEFAULT '',
  `user_id`       INT UNSIGNED NULL DEFAULT NULL,
  `user_name`     VARCHAR(100) NOT NULL DEFAULT 'System',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_movements_product` (`product_id`),
  CONSTRAINT `fk_movements_product` FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Key/value settings
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key`   VARCHAR(60) NOT NULL,
  `setting_value` TEXT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Seed: users  (admin / admin123  ·  cashier / cashier123)
-- -------------------------------------------------------------
INSERT IGNORE INTO `users` (`id`,`name`,`username`,`password_hash`,`role`,`active`) VALUES
(1,'Administrator','admin','$2y$10$stcMhJLjV3.TPmmXATw1TOylT3VQdTGWeY4KuyOn5DPHcQhW5F94u','admin',1),
(2,'Cashier One','cashier','$2y$10$tq74V4u4xKuBm0PO8m.aFe70Ew3QksXcowwzu88e8d3AsJXGN7mzy','cashier',1);

-- -------------------------------------------------------------
-- Seed: settings
-- -------------------------------------------------------------
INSERT IGNORE INTO `settings` (`setting_key`,`setting_value`) VALUES
('business_name','Aronium POS Web'),
('business_address','123 Main Street, Cagayan de Oro City'),
('business_phone','(088) 000-0000'),
('business_tin','000-000-000-000'),
('currency_symbol','₱'),
('currency_position','before'),
('tax_name','VAT'),
('tax_rate','12'),
('tax_mode','exclusive'),
('receipt_footer','Thank you for your purchase! Please come again.'),
('receipt_width','80'),
('low_stock_threshold','5'),
('allow_negative_stock','0'),
('sale_prefix','INV'),
('decimal_places','2');

-- -------------------------------------------------------------
-- Seed: categories
-- -------------------------------------------------------------
INSERT IGNORE INTO `categories` (`id`,`name`) VALUES
(1,'Beverages'),
(2,'Snacks'),
(3,'Groceries'),
(4,'Household'),
(5,'Personal Care');

-- -------------------------------------------------------------
-- Seed: products
-- -------------------------------------------------------------
INSERT IGNORE INTO `products`
 (`id`,`sku`,`barcode`,`name`,`category_id`,`cost_price`,`selling_price`,`stock_qty`,`reorder_level`,`unit`,`tax_exempt`,`active`) VALUES
(1,'BEV-001','480001','Bottled Water 500ml',1,10.00,20.00,120.000,20.000,'pc',0,1),
(2,'BEV-002','480002','Cola 1.5L',1,38.00,60.00,60.000,12.000,'pc',0,1),
(3,'BEV-003','480003','Iced Tea 1L',1,25.00,45.00,48.000,12.000,'pc',0,1),
(4,'BEV-004','480004','Coffee Sachet 3-in-1',1,6.00,12.00,200.000,30.000,'pc',0,1),
(5,'SNK-001','480005','Potato Chips 90g',2,30.00,55.00,40.000,10.000,'pc',0,1),
(6,'SNK-002','480006','Choco Bar 45g',2,18.00,30.00,75.000,15.000,'pc',0,1),
(7,'SNK-003','480007','Assorted Cookies 200g',2,45.00,75.00,25.000,8.000,'pc',0,1),
(8,'GRO-001','480008','Rice 5kg (Local)',3,210.00,265.00,30.000,8.000,'sack',1,1),
(9,'GRO-002','480009','Cooking Oil 1L',3,55.00,85.00,36.000,10.000,'pc',0,1),
(10,'GRO-003','480010','Instant Noodles (Pack of 5)',3,40.00,62.00,90.000,20.000,'pack',0,1),
(11,'GRO-004','480011','Sugar 1kg',3,48.00,68.00,44.000,10.000,'pc',1,1),
(12,'HSE-001','480012','Dishwashing Liquid 500ml',4,42.00,72.00,28.000,8.000,'pc',0,1),
(13,'HSE-002','480013','Laundry Powder 500g',4,55.00,89.00,22.000,8.000,'pc',0,1),
(14,'PRC-001','480014','Shampoo 200ml',5,95.00,145.00,18.000,6.000,'pc',0,1),
(15,'PRC-002','480015','Toothpaste 100g',5,58.00,92.00,26.000,6.000,'pc',0,1);
