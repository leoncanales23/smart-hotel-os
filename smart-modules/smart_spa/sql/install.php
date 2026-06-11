<?php
/**
 * SQL de instalación — smart_spa
 * Grupo Smart de Administración
 */
if (!defined('_PS_VERSION_')) { exit; }

$queries = [
    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_spa_facility` (
        `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_property`   INT UNSIGNED NOT NULL DEFAULT 1,
        `name`          VARCHAR(100) NOT NULL,
        `facility_type` VARCHAR(30) DEFAULT "TREATMENT_ROOM",
        `capacity`      TINYINT UNSIGNED DEFAULT 1,
        `description`   TEXT,
        `active`        TINYINT(1) DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_spa_service` (
        `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_property`      INT UNSIGNED NOT NULL DEFAULT 1,
        `name`             VARCHAR(200) NOT NULL,
        `name_en`          VARCHAR(200),
        `service_type`     VARCHAR(30) NOT NULL DEFAULT "MASSAGE",
        `description`      TEXT,
        `duration_min`     SMALLINT UNSIGNED NOT NULL DEFAULT 60,
        `price`            DECIMAL(10,2) NOT NULL,
        `price_couple`     DECIMAL(10,2) NULL,
        `is_couples`       TINYINT(1) DEFAULT 0,
        `requires_gender`  ENUM("","M","F") DEFAULT "",
        `min_age`          TINYINT UNSIGNED DEFAULT 18,
        `prep_time_min`    TINYINT UNSIGNED DEFAULT 15,
        `image_url`        VARCHAR(500),
        `active`           TINYINT(1) DEFAULT 1,
        `sort_order`       TINYINT UNSIGNED DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_spa_therapist` (
        `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_property`    INT UNSIGNED NOT NULL DEFAULT 1,
        `firstname`      VARCHAR(100) NOT NULL,
        `lastname`       VARCHAR(100) NOT NULL,
        `gender`         ENUM("M","F") DEFAULT "F",
        `specialties`    JSON,
        `languages`      JSON,
        `rating`         DECIMAL(3,2) DEFAULT 0.00,
        `total_sessions` SMALLINT UNSIGNED DEFAULT 0,
        `photo_url`      VARCHAR(500),
        `bio`            TEXT,
        `active`         TINYINT(1) DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_spa_schedule` (
        `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_therapist`  INT UNSIGNED NOT NULL,
        `work_date`     DATE NOT NULL,
        `start_time`    TIME NOT NULL,
        `end_time`      TIME NOT NULL,
        `block_type`    ENUM("available","break","blocked","off") DEFAULT "available",
        `notes`         VARCHAR(200),
        INDEX `idx_therapist_date` (`id_therapist`, `work_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_spa_booking` (
        `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_property`      INT UNSIGNED NOT NULL DEFAULT 1,
        `id_service`       INT UNSIGNED NOT NULL,
        `id_therapist`     INT UNSIGNED NULL,
        `id_facility`      INT UNSIGNED NULL,
        `id_customer`      INT UNSIGNED NOT NULL,
        `id_booking`       INT UNSIGNED NULL,
        `booking_ref`      VARCHAR(20) NOT NULL UNIQUE,
        `status`           VARCHAR(20) DEFAULT "CONFIRMED",
        `appointment_date` DATE NOT NULL,
        `start_time`       TIME NOT NULL,
        `end_time`         TIME NOT NULL,
        `guests`           TINYINT UNSIGNED DEFAULT 1,
        `price`            DECIMAL(10,2) NOT NULL,
        `notes`            TEXT,
        `health_notes`     TEXT,
        `confirmed_at`     DATETIME DEFAULT CURRENT_TIMESTAMP,
        `checkin_at`       DATETIME NULL,
        `completed_at`     DATETIME NULL,
        `cancelled_at`     DATETIME NULL,
        `rating`           TINYINT UNSIGNED NULL,
        `feedback`         TEXT,
        INDEX `idx_date`      (`appointment_date`, `status`),
        INDEX `idx_therapist` (`id_therapist`, `appointment_date`),
        INDEX `idx_customer`  (`id_customer`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_spa_gym_access` (
        `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_customer`  INT UNSIGNED NOT NULL,
        `id_booking`   INT UNSIGNED NULL,
        `area`         VARCHAR(50) DEFAULT "GYM",
        `entered_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
        `exited_at`    DATETIME NULL,
        INDEX `idx_customer_date` (`id_customer`, `entered_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
];

foreach ($queries as $sql) {
    if (!Db::getInstance()->execute($sql)) { return false; }
}
return true;
