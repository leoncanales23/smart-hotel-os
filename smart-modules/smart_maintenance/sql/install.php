<?php
/**
 * SQL de instalación — smart_maintenance
 * Grupo Smart de Administración
 */
if (!defined('_PS_VERSION_')) { exit; }

$queries = [
    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_asset` (
        `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_property`      INT UNSIGNED NOT NULL DEFAULT 1,
        `asset_code`       VARCHAR(30) NOT NULL,
        `name`             VARCHAR(200) NOT NULL,
        `asset_type`       VARCHAR(30) NOT NULL DEFAULT "ROOM",
        `id_room`          INT UNSIGNED NULL,
        `location`         VARCHAR(200),
        `brand`            VARCHAR(100),
        `model`            VARCHAR(100),
        `serial_number`    VARCHAR(100),
        `purchase_date`    DATE NULL,
        `warranty_until`   DATE NULL,
        `last_service`     DATE NULL,
        `next_service`     DATE NULL,
        `service_freq_days`SMALLINT UNSIGNED DEFAULT 90,
        `status`           ENUM("operational","maintenance","oos","disposed") DEFAULT "operational",
        `notes`            TEXT,
        `active`           TINYINT(1) DEFAULT 1,
        UNIQUE KEY `uk_property_code` (`id_property`, `asset_code`),
        INDEX `idx_next_service` (`next_service`),
        INDEX `idx_type` (`asset_type`, `active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_maint_ticket` (
        `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_property`      INT UNSIGNED NOT NULL DEFAULT 1,
        `ticket_number`    VARCHAR(20) NOT NULL UNIQUE,
        `ticket_type`      VARCHAR(20) NOT NULL DEFAULT "CORRECTIVE",
        `id_asset`         INT UNSIGNED NULL,
        `id_room`          INT UNSIGNED NULL,
        `id_booking`       INT UNSIGNED NULL,
        `title`            VARCHAR(300) NOT NULL,
        `description`      TEXT,
        `priority`         TINYINT UNSIGNED DEFAULT 2,
        `status`           ENUM("open","assigned","in_progress","pending_parts",
                                 "completed","verified","closed","cancelled") DEFAULT "open",
        `id_reported_by`   INT UNSIGNED NULL,
        `id_assigned_to`   INT UNSIGNED NULL,
        `id_verified_by`   INT UNSIGNED NULL,
        `photo_urls`       JSON,
        `parts_needed`     TEXT,
        `parts_cost`       DECIMAL(10,2) DEFAULT 0.00,
        `labor_hours`      DECIMAL(5,2) DEFAULT 0.00,
        `resolution_notes` TEXT,
        `sla_deadline`     DATETIME NULL,
        `sla_breached`     TINYINT(1) DEFAULT 0,
        `opened_at`        DATETIME DEFAULT CURRENT_TIMESTAMP,
        `assigned_at`      DATETIME NULL,
        `started_at`       DATETIME NULL,
        `completed_at`     DATETIME NULL,
        `verified_at`      DATETIME NULL,
        `closed_at`        DATETIME NULL,
        INDEX `idx_status_priority` (`status`, `priority`),
        INDEX `idx_room`            (`id_room`),
        INDEX `idx_asset`           (`id_asset`),
        INDEX `idx_sla`             (`sla_deadline`, `sla_breached`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_maint_comment` (
        `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_ticket`    INT UNSIGNED NOT NULL,
        `id_employee`  INT UNSIGNED NOT NULL,
        `comment`      TEXT NOT NULL,
        `photo_urls`   JSON,
        `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_ticket` (`id_ticket`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_maint_plan` (
        `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_property`     INT UNSIGNED NOT NULL DEFAULT 1,
        `name`            VARCHAR(200) NOT NULL,
        `id_asset_type`   VARCHAR(30),
        `frequency_days`  SMALLINT UNSIGNED NOT NULL DEFAULT 90,
        `checklist`       JSON,
        `estimated_hours` DECIMAL(5,2) DEFAULT 2.00,
        `active`          TINYINT(1) DEFAULT 1,
        `last_run`        DATE NULL,
        `next_run`        DATE NULL,
        INDEX `idx_next_run` (`next_run`, `active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
];

foreach ($queries as $sql) {
    if (!Db::getInstance()->execute($sql)) { return false; }
}
return true;
