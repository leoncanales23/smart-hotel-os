<?php
/**
 * SQL de instalación — smart_guestprofile
 * Grupo Smart de Administración
 */
if (!defined('_PS_VERSION_')) { exit; }

$queries = [
    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_guest_profile` (
        `id`                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_customer`        INT UNSIGNED NOT NULL UNIQUE,
        `guest_code`         VARCHAR(20) UNIQUE,
        `nationality`        CHAR(2),
        `passport_number`    VARCHAR(50),
        `date_of_birth`      DATE NULL,
        `gender`             ENUM("M","F","X","") DEFAULT "",
        `language_code`      VARCHAR(5) DEFAULT "es",
        `segment`            VARCHAR(20) DEFAULT "STANDARD",
        `vip_level`          TINYINT UNSIGNED DEFAULT 0,
        `loyalty_points`     INT UNSIGNED DEFAULT 0,
        `total_stays`        SMALLINT UNSIGNED DEFAULT 0,
        `total_nights`       SMALLINT UNSIGNED DEFAULT 0,
        `total_spent`        DECIMAL(14,2) DEFAULT 0.00,
        `first_stay_date`    DATE NULL,
        `last_stay_date`     DATE NULL,
        `last_stay_property` INT UNSIGNED NULL,
        `avg_daily_spend`    DECIMAL(10,2) DEFAULT 0.00,
        `nps_score`          TINYINT NULL,
        `is_blocked`         TINYINT(1) DEFAULT 0,
        `block_reason`       TEXT NULL,
        `internal_notes`     TEXT NULL,
        `concierge_notes`    TEXT NULL,
        `created_at`         DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at`         DATETIME ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_segment`   (`segment`),
        INDEX `idx_vip`       (`vip_level`),
        INDEX `idx_last_stay` (`last_stay_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_guest_preference` (
        `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_guest`       INT UNSIGNED NOT NULL,
        `category`       VARCHAR(30) NOT NULL,
        `preference_key` VARCHAR(100) NOT NULL,
        `preference_val` TEXT,
        `source`         ENUM("staff","guest","booking","system") DEFAULT "staff",
        `confirmed`      TINYINT(1) DEFAULT 1,
        `id_property`    INT UNSIGNED NULL,
        `created_by`     INT UNSIGNED NULL,
        `created_at`     DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at`     DATETIME ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_guest_cat`    (`id_guest`, `category`),
        UNIQUE KEY `uk_guest_key_prop` (`id_guest`, `preference_key`, `id_property`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_guest_allergy` (
        `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_guest`     INT UNSIGNED NOT NULL,
        `allergy_type` VARCHAR(50) NOT NULL,
        `severity`     ENUM("intolerance","allergy","anaphylaxis") DEFAULT "allergy",
        `notes`        VARCHAR(300),
        `verified`     TINYINT(1) DEFAULT 0,
        `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_guest` (`id_guest`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_guest_occasion` (
        `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_guest`      INT UNSIGNED NOT NULL,
        `occasion_type` VARCHAR(30) NOT NULL,
        `occasion_date` DATE NOT NULL,
        `partner_name`  VARCHAR(100) NULL,
        `notes`         TEXT,
        `remind_days`   TINYINT UNSIGNED DEFAULT 7,
        `active`        TINYINT(1) DEFAULT 1,
        INDEX `idx_guest` (`id_guest`),
        INDEX `idx_date`  (`occasion_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_guest_stay_history` (
        `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_guest`     INT UNSIGNED NOT NULL,
        `id_booking`   INT UNSIGNED NOT NULL,
        `id_property`  INT UNSIGNED NOT NULL DEFAULT 1,
        `id_room_type` INT UNSIGNED,
        `id_room`      INT UNSIGNED,
        `checkin_date` DATE NOT NULL,
        `checkout_date`DATE NOT NULL,
        `nights`       TINYINT UNSIGNED,
        `adults`       TINYINT UNSIGNED DEFAULT 1,
        `children`     TINYINT UNSIGNED DEFAULT 0,
        `total_spent`  DECIMAL(12,2) DEFAULT 0.00,
        `room_rate`    DECIMAL(10,2) DEFAULT 0.00,
        `rate_plan`    VARCHAR(50),
        `channel`      VARCHAR(30),
        `nps_score`    TINYINT NULL,
        `nps_comment`  TEXT NULL,
        `was_vip`      TINYINT(1) DEFAULT 0,
        `staff_notes`  TEXT,
        `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_guest`   (`id_guest`),
        INDEX `idx_booking` (`id_booking`),
        INDEX `idx_checkin` (`checkin_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_guest_tag` (
        `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_guest`   INT UNSIGNED NOT NULL,
        `tag`        VARCHAR(50) NOT NULL,
        `color`      VARCHAR(7) DEFAULT "#C9A84C",
        `created_by` INT UNSIGNED,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uk_guest_tag` (`id_guest`, `tag`),
        INDEX `idx_tag` (`tag`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
];

foreach ($queries as $sql) {
    if (!Db::getInstance()->execute($sql)) { return false; }
}
return true;
