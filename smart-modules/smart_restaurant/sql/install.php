<?php
/**
 * SQL de instalación — smart_restaurant
 * Grupo Smart de Administración
 */
if (!defined('_PS_VERSION_')) { exit; }

$queries = [
    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_fb_outlet` (
        `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_property`   INT UNSIGNED NOT NULL DEFAULT 1,
        `name`          VARCHAR(100) NOT NULL,
        `outlet_type`   VARCHAR(20) NOT NULL DEFAULT "RESTAURANT",
        `code`          VARCHAR(10) NOT NULL,
        `floor`         TINYINT UNSIGNED DEFAULT 1,
        `capacity`      SMALLINT UNSIGNED DEFAULT 0,
        `opening_time`  TIME DEFAULT "07:00:00",
        `closing_time`  TIME DEFAULT "23:00:00",
        `active`        TINYINT(1) DEFAULT 1,
        UNIQUE KEY `uk_property_code` (`id_property`, `code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_fb_table` (
        `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_outlet`     INT UNSIGNED NOT NULL,
        `table_number`  VARCHAR(10) NOT NULL,
        `capacity`      TINYINT UNSIGNED DEFAULT 4,
        `status`        VARCHAR(20) DEFAULT "AVAILABLE",
        `current_order` INT UNSIGNED NULL,
        `section`       VARCHAR(50),
        `pos_x`         SMALLINT DEFAULT 0,
        `pos_y`         SMALLINT DEFAULT 0,
        `active`        TINYINT(1) DEFAULT 1,
        UNIQUE KEY `uk_outlet_table` (`id_outlet`, `table_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_fb_menu_category` (
        `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_outlet`      INT UNSIGNED NULL,
        `name`           VARCHAR(100) NOT NULL,
        `name_en`        VARCHAR(100),
        `sort_order`     TINYINT UNSIGNED DEFAULT 0,
        `active`         TINYINT(1) DEFAULT 1,
        `available_from` TIME NULL,
        `available_to`   TIME NULL,
        `image_url`      VARCHAR(500)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_fb_menu_item` (
        `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_category`      INT UNSIGNED NOT NULL,
        `id_outlet`        INT UNSIGNED NULL,
        `name`             VARCHAR(200) NOT NULL,
        `name_en`          VARCHAR(200),
        `description`      TEXT,
        `price`            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `price_room_svc`   DECIMAL(10,2) DEFAULT NULL,
        `cost`             DECIMAL(10,2) DEFAULT 0.00,
        `sku`              VARCHAR(50),
        `unit`             VARCHAR(20) DEFAULT "porción",
        `prep_time_min`    TINYINT UNSIGNED DEFAULT 15,
        `is_vegan`         TINYINT(1) DEFAULT 0,
        `is_vegetarian`    TINYINT(1) DEFAULT 0,
        `is_gluten_free`   TINYINT(1) DEFAULT 0,
        `allergens`        JSON,
        `tags`             JSON,
        `image_url`        VARCHAR(500),
        `available`        TINYINT(1) DEFAULT 1,
        `active`           TINYINT(1) DEFAULT 1,
        `sort_order`       TINYINT UNSIGNED DEFAULT 0,
        INDEX `idx_category` (`id_category`, `active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_fb_order` (
        `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_outlet`       INT UNSIGNED NOT NULL,
        `id_table`        INT UNSIGNED NULL,
        `id_booking`      INT UNSIGNED NULL,
        `id_room`         INT UNSIGNED NULL,
        `order_number`    VARCHAR(20) NOT NULL UNIQUE,
        `status`          VARCHAR(20) DEFAULT "OPEN",
        `covers`          TINYINT UNSIGNED DEFAULT 1,
        `subtotal`        DECIMAL(10,2) DEFAULT 0.00,
        `discount`        DECIMAL(10,2) DEFAULT 0.00,
        `tax`             DECIMAL(10,2) DEFAULT 0.00,
        `service_charge`  DECIMAL(10,2) DEFAULT 0.00,
        `total`           DECIMAL(10,2) DEFAULT 0.00,
        `payment_method`  VARCHAR(20) NULL,
        `payment_ref`     VARCHAR(100) NULL,
        `id_waiter`       INT UNSIGNED NULL,
        `id_cashier`      INT UNSIGNED NULL,
        `notes`           TEXT,
        `opened_at`       DATETIME DEFAULT CURRENT_TIMESTAMP,
        `sent_at`         DATETIME NULL,
        `closed_at`       DATETIME NULL,
        INDEX `idx_outlet_status` (`id_outlet`, `status`),
        INDEX `idx_table`         (`id_table`),
        INDEX `idx_booking`       (`id_booking`),
        INDEX `idx_date`          (`opened_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_fb_order_line` (
        `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_order`    INT UNSIGNED NOT NULL,
        `id_item`     INT UNSIGNED NOT NULL,
        `item_name`   VARCHAR(200) NOT NULL,
        `unit_price`  DECIMAL(10,2) NOT NULL,
        `quantity`    DECIMAL(5,2) NOT NULL DEFAULT 1,
        `discount_pct`DECIMAL(5,2) DEFAULT 0.00,
        `line_total`  DECIMAL(10,2) NOT NULL,
        `modifiers`   JSON,
        `notes`       VARCHAR(300),
        `status`      ENUM("ordered","in_prep","ready","served","voided") DEFAULT "ordered",
        `voided_reason`VARCHAR(200) NULL,
        `added_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
        `served_at`   DATETIME NULL,
        INDEX `idx_order` (`id_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_fb_shift` (
        `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_outlet`     INT UNSIGNED NOT NULL,
        `id_cashier`    INT UNSIGNED NOT NULL,
        `opening_float` DECIMAL(10,2) DEFAULT 0.00,
        `closing_float` DECIMAL(10,2) NULL,
        `total_sales`   DECIMAL(12,2) DEFAULT 0.00,
        `total_covers`  SMALLINT UNSIGNED DEFAULT 0,
        `opened_at`     DATETIME DEFAULT CURRENT_TIMESTAMP,
        `closed_at`     DATETIME NULL,
        `notes`         TEXT,
        INDEX `idx_outlet_date` (`id_outlet`, `opened_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
];

foreach ($queries as $sql) {
    if (!Db::getInstance()->execute($sql)) { return false; }
}
return true;
