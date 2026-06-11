<?php
/**
 * SQL de instalación — smart_ratemanager
 * Grupo Smart de Administración
 */
if (!defined('_PS_VERSION_')) { exit; }

$queries = [
    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_rate_plan` (
        `id`                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_property`          INT UNSIGNED NOT NULL DEFAULT 1,
        `code`                 VARCHAR(20) NOT NULL,
        `name`                 VARCHAR(100) NOT NULL,
        `plan_type`            VARCHAR(20) NOT NULL DEFAULT "BAR",
        `description`          TEXT,
        `meal_plan`            ENUM("RO","BB","HB","FB","AI") DEFAULT "BB",
        `cancellation_policy`  TEXT,
        `is_refundable`        TINYINT(1) DEFAULT 1,
        `advance_booking_min`  SMALLINT UNSIGNED DEFAULT 0,
        `advance_booking_max`  SMALLINT UNSIGNED DEFAULT 365,
        `min_stay`             TINYINT UNSIGNED DEFAULT 1,
        `max_stay`             TINYINT UNSIGNED DEFAULT 30,
        `active`               TINYINT(1) DEFAULT 1,
        `created_at`           DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uk_property_code` (`id_property`, `code`),
        INDEX `idx_type` (`plan_type`, `active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_rate` (
        `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_property`      INT UNSIGNED NOT NULL DEFAULT 1,
        `id_rate_plan`     INT UNSIGNED NOT NULL,
        `id_room_type`     INT UNSIGNED NOT NULL,
        `id_channel`       VARCHAR(20) DEFAULT "DIRECT_WEB",
        `date_from`        DATE NOT NULL,
        `date_to`          DATE NOT NULL,
        `day_mon`          TINYINT(1) DEFAULT 1,
        `day_tue`          TINYINT(1) DEFAULT 1,
        `day_wed`          TINYINT(1) DEFAULT 1,
        `day_thu`          TINYINT(1) DEFAULT 1,
        `day_fri`          TINYINT(1) DEFAULT 1,
        `day_sat`          TINYINT(1) DEFAULT 1,
        `day_sun`          TINYINT(1) DEFAULT 1,
        `price_1`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `price_2`          DECIMAL(10,2) DEFAULT 0.00,
        `price_extra_adult`DECIMAL(10,2) DEFAULT 0.00,
        `price_child`      DECIMAL(10,2) DEFAULT 0.00,
        `currency`         CHAR(3) DEFAULT "CLP",
        `allotment`        SMALLINT UNSIGNED DEFAULT NULL,
        `allotment_used`   SMALLINT UNSIGNED DEFAULT 0,
        `is_closed`        TINYINT(1) DEFAULT 0,
        `is_stop_sell`     TINYINT(1) DEFAULT 0,
        `min_stay`         TINYINT UNSIGNED DEFAULT 1,
        `created_at`       DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at`       DATETIME ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_room_dates`    (`id_room_type`, `date_from`, `date_to`),
        INDEX `idx_plan_dates`    (`id_rate_plan`, `date_from`, `date_to`),
        INDEX `idx_channel_dates` (`id_channel`, `date_from`, `date_to`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_rate_change_log` (
        `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_rate`    INT UNSIGNED NOT NULL,
        `changed_by` INT UNSIGNED NULL,
        `old_price`  DECIMAL(10,2),
        `new_price`  DECIMAL(10,2),
        `reason`     VARCHAR(200),
        `changed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_rate`    (`id_rate`),
        INDEX `idx_changed` (`changed_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_yield_config` (
        `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_property`      INT UNSIGNED NOT NULL DEFAULT 1 UNIQUE,
        `enabled`          TINYINT(1) DEFAULT 0,
        `base_occupancy`   DECIMAL(5,2) DEFAULT 70.00,
        `increment_high`   DECIMAL(5,2) DEFAULT 20.00,
        `decrement_low`    DECIMAL(5,2) DEFAULT 15.00,
        `max_price_floor`  DECIMAL(5,2) DEFAULT 0.00,
        `max_price_ceil`   DECIMAL(5,2) DEFAULT 200.00,
        `auto_apply`       TINYINT(1) DEFAULT 0,
        `notify_manager`   TINYINT(1) DEFAULT 1,
        `updated_at`       DATETIME ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
];

foreach ($queries as $sql) {
    if (!Db::getInstance()->execute($sql)) { return false; }
}
return true;
