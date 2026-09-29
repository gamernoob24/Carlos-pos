/*!40101 SET NAMES utf8mb4 */;

-- =============================================================
--  Carlo's Burger POS & Inventory System — database schema
--  MySQL 5.7+ / MariaDB 10.2+   (charset: utf8mb4)
--  Import: phpMyAdmin -> Import  |  or  mysql -u root -p < Carlos_pos.sql
--  Safe to re-run: uses CREATE TABLE IF NOT EXISTS / INSERT IGNORE
--
--  SRS entities: users · orders(sales) · order_items(sale_items)
--                products(menu) · ingredients · shifts
-- =============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `Carlos_pos`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `Carlos_pos`;

-- -------------------------------------------------------------
-- Users (Cashiers & Managers)
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
-- Menu categories
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Products / menu items
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
-- Ingredients (raw inventory)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ingredients` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(120) NOT NULL,
  `unit`          VARCHAR(20)  NOT NULL DEFAULT 'g',
  `stock_qty`     DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  `reorder_level` DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  `cost_per_unit` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `active`        TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ingredients_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Recipes: how much of each ingredient ONE product consumes
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_ingredients` (
  `product_id`    INT UNSIGNED NOT NULL,
  `ingredient_id` INT UNSIGNED NOT NULL,
  `qty_per_unit`  DECIMAL(12,3) NOT NULL DEFAULT 1.000,
  PRIMARY KEY (`product_id`,`ingredient_id`),
  CONSTRAINT `fk_recipe_product` FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_recipe_ingredient` FOREIGN KEY (`ingredient_id`)
    REFERENCES `ingredients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Order modifiers (extra cheese, no onions, add bacon …)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `modifiers` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(80) NOT NULL,
  `price_delta` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `active`      TINYINT(1)  NOT NULL DEFAULT 1,
  `sort_order`  INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_modifiers_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Cash drawer shifts (one open shift per cashier)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `shifts` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED NOT NULL,
  `user_name`     VARCHAR(100) NOT NULL DEFAULT '',
  `opening_cash`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `closing_cash`  DECIMAL(12,2) NULL DEFAULT NULL,
  `expected_cash` DECIMAL(12,2) NULL DEFAULT NULL,
  `opened_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `closed_at`     DATETIME NULL DEFAULT NULL,
  `status`        ENUM('open','closed') NOT NULL DEFAULT 'open',
  `note`          VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_shifts_user` (`user_id`),
  CONSTRAINT `fk_shifts_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Orders
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sales` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sale_no`        VARCHAR(32)  NOT NULL,
  `user_id`        INT UNSIGNED NULL DEFAULT NULL,
  `user_name`      VARCHAR(100) NOT NULL DEFAULT '',
  `shift_id`       INT UNSIGNED NULL DEFAULT NULL,
  `customer_name`  VARCHAR(150) NULL DEFAULT NULL,
  `subtotal`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `tax_total`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total`          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `paid_amount`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `change_amount`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `payment_method` ENUM('cash','card','ewallet') NOT NULL DEFAULT 'cash',
  `status`         ENUM('queued','preparing','completed','voided') NOT NULL DEFAULT 'queued',
  `note`           VARCHAR(255) NULL DEFAULT NULL,
  `ready_at`       DATETIME NULL DEFAULT NULL,
  `voided_at`      DATETIME NULL DEFAULT NULL,
  `voided_by`      VARCHAR(100) NULL DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sales_no` (`sale_no`),
  KEY `ix_sales_created` (`created_at`),
  KEY `ix_sales_status` (`status`),
  KEY `ix_sales_shift` (`shift_id`),
  CONSTRAINT `fk_sales_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_sales_shift` FOREIGN KEY (`shift_id`)
    REFERENCES `shifts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Order line items
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sale_items` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sale_id`         INT UNSIGNED NOT NULL,
  `product_id`      INT UNSIGNED NULL DEFAULT NULL,
  `product_name`    VARCHAR(200) NOT NULL,
  `sku`             VARCHAR(64)  NOT NULL DEFAULT '',
  `qty`             DECIMAL(12,3) NOT NULL DEFAULT 1.000,
  `unit_price`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `modifiers_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
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
-- Modifiers chosen per order line (snapshot)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sale_item_modifiers` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sale_item_id`  INT UNSIGNED NOT NULL,
  `modifier_id`   INT UNSIGNED NULL DEFAULT NULL,
  `modifier_name` VARCHAR(80) NOT NULL,
  `price_delta`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `ix_sim_item` (`sale_item_id`),
  CONSTRAINT `fk_sim_item` FOREIGN KEY (`sale_item_id`)
    REFERENCES `sale_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Ingredients consumed per order line (snapshot, for exact voids)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sale_item_ingredients` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sale_item_id`    INT UNSIGNED NOT NULL,
  `ingredient_id`   INT UNSIGNED NULL DEFAULT NULL,
  `ingredient_name` VARCHAR(120) NOT NULL DEFAULT '',
  `qty_used`        DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  PRIMARY KEY (`id`),
  KEY `ix_sii_item` (`sale_item_id`),
  CONSTRAINT `fk_sii_item` FOREIGN KEY (`sale_item_id`)
    REFERENCES `sale_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Stock movements (products AND ingredients)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_movements` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`     INT UNSIGNED NULL DEFAULT NULL,
  `ingredient_id`  INT UNSIGNED NULL DEFAULT NULL,
  `qty_change`     DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  `balance_after`  DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  `reason`         VARCHAR(150) NOT NULL DEFAULT '',
  `user_id`        INT UNSIGNED NULL DEFAULT NULL,
  `user_name`      VARCHAR(100) NOT NULL DEFAULT 'System',
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_movements_product` (`product_id`),
  KEY `ix_movements_ingredient` (`ingredient_id`),
  CONSTRAINT `fk_movements_product` FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_movements_ingredient` FOREIGN KEY (`ingredient_id`)
    REFERENCES `ingredients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Settings
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key`   VARCHAR(60) NOT NULL,
  `setting_value` TEXT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
--  STARTER DATA — Carlo's Burger (Guiwan, Zamboanga City)
-- =============================================================

INSERT IGNORE INTO `users` (`id`,`name`,`username`,`password_hash`,`role`,`active`) VALUES
(1,'Carlo (Owner)','admin','$2y$10$stcMhJLjV3.TPmmXATw1TOylT3VQdTGWeY4KuyOn5DPHcQhW5F94u','admin',1),
(2,'Cashier One','cashier','$2y$10$tq74V4u4xKuBm0PO8m.aFe70Ew3QksXcowwzu88e8d3AsJXGN7mzy','cashier',1);

INSERT IGNORE INTO `settings` (`setting_key`,`setting_value`) VALUES
('business_name',"Carlo's Burger"),
('business_address','Guiwan, Zamboanga City'),
('business_phone','(062) 000-0000'),
('business_tin','000-000-000-000'),
('currency_symbol','₱'),
('currency_position','before'),
('tax_name','VAT'),
('tax_rate','12'),
('tax_mode','inclusive'),
('receipt_footer','Salamat! Thank you for eating at Carlo''s Burger.'),
('receipt_width','80'),
('low_stock_threshold','5'),
('allow_negative_stock','0'),
('sale_prefix','ORD'),
('decimal_places','2');

INSERT IGNORE INTO `categories` (`id`,`name`) VALUES
(1,'Burgers'),(2,'Sides'),(3,'Beverages'),(4,'Combos');

INSERT IGNORE INTO `products`
 (`id`,`sku`,`barcode`,`name`,`category_id`,`cost_price`,`selling_price`,`stock_qty`,`reorder_level`,`unit`,`tax_exempt`,`active`) VALUES
(1,'BUR-001','900001',"Carlo's Special Burger",1,58.00,95.00,40.000,10.000,'pc',0,1),
(2,'BUR-002','900002','Cheeseburger',1,48.00,85.00,40.000,10.000,'pc',0,1),
(3,'BUR-003','900003','Double Cheeseburger',1,79.00,135.00,30.000,8.000,'pc',0,1),
(4,'BUR-004','900004','Bacon Burger',1,70.00,120.00,30.000,8.000,'pc',0,1),
(5,'BUR-005','900005','Chicken Burger',1,52.00,90.00,35.000,8.000,'pc',0,1),
(6,'BUR-006','900006','Veggie Burger',1,45.00,80.00,20.000,5.000,'pc',0,1),
(7,'SID-001','900007','French Fries (Regular)',2,18.00,45.00,60.000,15.000,'pc',0,1),
(8,'SID-002','900008','French Fries (Large)',2,26.00,65.00,60.000,15.000,'pc',0,1),
(9,'SID-003','900009','Onion Rings',2,24.00,55.00,40.000,10.000,'pc',0,1),
(10,'SID-004','900010','Chicken Nuggets (6 pcs)',2,38.00,70.00,35.000,10.000,'pc',0,1),
(11,'BEV-001','900011','Iced Tea',3,9.00,30.00,80.000,20.000,'cup',0,1),
(12,'BEV-002','900012','Coca-Cola',3,18.00,35.00,90.000,20.000,'bottle',0,1),
(13,'BEV-003','900013','Bottled Water',3,10.00,20.00,100.000,20.000,'bottle',0,1),
(14,'BEV-004','900014','Brewed Coffee',3,14.00,40.00,60.000,15.000,'cup',0,1),
(15,'CMB-001','900015','Burger Combo (burger + fries + drink)',4,105.00,165.00,25.000,6.000,'set',0,1);

INSERT IGNORE INTO `ingredients`
 (`id`,`name`,`unit`,`stock_qty`,`reorder_level`,`cost_per_unit`,`active`) VALUES
(1,'Beef patty','pc',120.000,30.000,22.0000,1),
(2,'Burger bun','pc',150.000,40.000,6.5000,1),
(3,'Cheese slice','pc',200.000,50.000,4.0000,1),
(4,'Bacon strip','pc',100.000,25.000,9.0000,1),
(5,'Lettuce','g',2000.000,500.000,0.0600,1),
(6,'Tomato','g',1500.000,400.000,0.0500,1),
(7,'Onion','g',1500.000,400.000,0.0450,1),
(8,'Pickle slice','pc',300.000,80.000,1.2000,1),
(9,'Special sauce','ml',3000.000,800.000,0.0300,1),
(10,'Chicken fillet','pc',80.000,20.000,26.0000,1),
(11,'Veggie patty','pc',40.000,10.000,18.0000,1),
(12,'Potatoes','g',25000.000,6000.000,0.0350,1),
(13,'Cooking oil','ml',12000.000,3000.000,0.0400,1),
(14,'Flour','g',5000.000,1000.000,0.0400,1),
(15,'Breadcrumbs','g',3000.000,800.000,0.0600,1),
(16,'Chicken nugget','pc',300.000,80.000,4.5000,1),
(17,'Tea powder','g',800.000,200.000,0.4000,1),
(18,'Sugar','g',5000.000,1200.000,0.0500,1),
(19,'Ice','g',20000.000,5000.000,0.0100,1),
(20,'Paper cup','pc',400.000,100.000,1.8000,1),
(21,'Soda in can','pc',150.000,40.000,16.0000,1),
(22,'Bottled water','pc',150.000,40.000,9.5000,1),
(23,'Coffee beans','g',900.000,250.000,0.9000,1);

-- Recipes: ingredient quantity consumed by ONE unit of each product
INSERT IGNORE INTO `product_ingredients` (`product_id`,`ingredient_id`,`qty_per_unit`) VALUES
(1,1,1.000),(1,2,1.000),(1,3,1.000),(1,5,20.000),(1,6,25.000),(1,9,30.000),
(2,1,1.000),(2,2,1.000),(2,3,1.000),(2,8,2.000),(2,9,20.000),
(3,1,2.000),(3,2,1.000),(3,3,2.000),(3,8,2.000),(3,9,30.000),
(4,1,1.000),(4,2,1.000),(4,3,1.000),(4,4,2.000),(4,9,20.000),
(5,10,1.000),(5,2,1.000),(5,5,15.000),(5,9,20.000),
(6,11,1.000),(6,2,1.000),(6,5,15.000),(6,6,20.000),(6,9,15.000),
(7,12,150.000),(7,13,30.000),
(8,12,250.000),(8,13,45.000),
(9,7,100.000),(9,14,50.000),(9,15,40.000),(9,13,40.000),
(10,16,6.000),(10,13,30.000),
(11,20,1.000),(11,17,10.000),(11,18,25.000),(11,19,150.000),
(12,21,1.000),
(13,22,1.000),
(14,20,1.000),(14,23,15.000),(14,18,10.000),
(15,1,1.000),(15,2,1.000),(15,3,1.000),(15,9,20.000),(15,12,150.000),(15,13,30.000),(15,21,1.000);

INSERT IGNORE INTO `modifiers` (`id`,`name`,`price_delta`,`active`,`sort_order`) VALUES
(1,'No onions',0.00,1,1),
(2,'No pickles',0.00,1,2),
(3,'No lettuce',0.00,1,3),
(4,'Extra cheese',15.00,1,4),
(5,'Extra patty',35.00,1,5),
(6,'Add bacon',25.00,1,6),
(7,'Add egg',15.00,1,7),
(8,'Extra sauce',5.00,1,8),
(9,'Less ice',0.00,1,9),
(10,'No sugar',0.00,1,10);
