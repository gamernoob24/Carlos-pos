/*!40101 SET NAMES utf8mb4 */;

-- =============================================================
--  Carlo's Burger POS — migration: v1 (generic retail) → v2 (SRS-complete)
--  Use ONLY if you already imported an older Carlos_pos.sql and have data.
--  Fresh installs: just import database/Carlos_pos.sql instead.
--  Run in phpMyAdmin → SQL, or: mysql -u root -p Carlos_pos < migrate_v2.sql
--  Safe to run twice: every step checks before altering.
-- =============================================================

USE `Carlos_pos`;

-- 1. New tables ------------------------------------------------
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

CREATE TABLE IF NOT EXISTS `modifiers` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(80) NOT NULL,
  `price_delta` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `active`      TINYINT(1)  NOT NULL DEFAULT 1,
  `sort_order`  INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_modifiers_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- 2. New / changed columns -------------------------------------
SET @db := DATABASE();

-- sales.shift_id
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA=@db AND TABLE_NAME='sales' AND COLUMN_NAME='shift_id');
SET @sql := IF(@exists=0,
  'ALTER TABLE sales ADD COLUMN shift_id INT UNSIGNED NULL DEFAULT NULL AFTER user_name, ADD KEY ix_sales_shift (shift_id), ADD CONSTRAINT fk_sales_shift FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE SET NULL ON UPDATE CASCADE',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- sales.status: add the kitchen states
ALTER TABLE `sales`
  MODIFY `status` ENUM('queued','preparing','completed','voided') NOT NULL DEFAULT 'queued';

-- sales.ready_at
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA=@db AND TABLE_NAME='sales' AND COLUMN_NAME='ready_at');
SET @sql := IF(@exists=0, 'ALTER TABLE sales ADD COLUMN ready_at DATETIME NULL DEFAULT NULL AFTER note', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- sale_items.modifiers_total
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA=@db AND TABLE_NAME='sale_items' AND COLUMN_NAME='modifiers_total');
SET @sql := IF(@exists=0,
  'ALTER TABLE sale_items ADD COLUMN modifiers_total DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER unit_price', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- stock_movements.ingredient_id
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA=@db AND TABLE_NAME='stock_movements' AND COLUMN_NAME='ingredient_id');
SET @sql := IF(@exists=0,
  'ALTER TABLE stock_movements MODIFY product_id INT UNSIGNED NULL DEFAULT NULL, ADD COLUMN ingredient_id INT UNSIGNED NULL DEFAULT NULL AFTER product_id, ADD KEY ix_movements_ingredient (ingredient_id), ADD CONSTRAINT fk_movements_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE ON UPDATE CASCADE',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 3. Starter modifiers ----------------------------------------
INSERT IGNORE INTO `modifiers` (`id`,`name`,`price_delta`,`active`,`sort_order`) VALUES
(1,'No onions',0.00,1,1),(2,'No pickles',0.00,1,2),(3,'No lettuce',0.00,1,3),
(4,'Extra cheese',15.00,1,4),(5,'Extra patty',35.00,1,5),(6,'Add bacon',25.00,1,6),
(7,'Add egg',15.00,1,7),(8,'Extra sauce',5.00,1,8),(9,'Less ice',0.00,1,9),
(10,'No sugar',0.00,1,10);

-- Done. Next steps in the app:
--   Inventory → Ingredients  (add your raw stock)
--   Inventory → Recipes      (map ingredients to each menu item)
--   Shifts                   (open a drawer before selling)
