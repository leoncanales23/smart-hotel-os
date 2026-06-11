<?php
/**
 * SQL de instalación — smart_dashboard
 * Grupo Smart de Administración
 */
if (!defined('_PS_VERSION_')) { exit; }

$queries = [
    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_dashboard_snapshot` (
        `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_property`     INT UNSIGNED NOT NULL DEFAULT 1,
        `snapshot_date`   DATE NOT NULL,
        `total_rooms`     SMALLINT UNSIGNED DEFAULT 0,
        `occupied_rooms`  SMALLINT UNSIGNED DEFAULT 0,
        `arrivals`        SMALLINT UNSIGNED DEFAULT 0,
        `departures`      SMALLINT UNSIGNED DEFAULT 0,
        `in_house`        SMALLINT UNSIGNED DEFAULT 0,
        `no_shows`        SMALLINT UNSIGNED DEFAULT 0,
        `cancellations`   SMALLINT UNSIGNED DEFAULT 0,
        `adr`             DECIMAL(10,2) DEFAULT 0.00,
        `revpar`          DECIMAL(10,2) DEFAULT 0.00,
        `total_revenue`   DECIMAL(12,2) DEFAULT 0.00,
        `rooms_revenue`   DECIMAL(12,2) DEFAULT 0.00,
        `fnb_revenue`     DECIMAL(12,2) DEFAULT 0.00,
        `spa_revenue`     DECIMAL(12,2) DEFAULT 0.00,
        `other_revenue`   DECIMAL(12,2) DEFAULT 0.00,
        `nps_score`       DECIMAL(5,2) DEFAULT NULL,
        `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uk_property_date` (`id_property`, `snapshot_date`),
        INDEX `idx_date` (`snapshot_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_dashboard_alert` (
        `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_property`  INT UNSIGNED NOT NULL DEFAULT 1,
        `alert_type`   VARCHAR(50) NOT NULL,
        `severity`     ENUM("info","warning","critical") DEFAULT "info",
        `title`        VARCHAR(200) NOT NULL,
        `message`      TEXT,
        `data`         JSON,
        `read_by`      INT UNSIGNED NULL,
        `read_at`      DATETIME NULL,
        `resolved`     TINYINT(1) DEFAULT 0,
        `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_property_resolved` (`id_property`, `resolved`),
        INDEX `idx_severity` (`severity`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_dashboard_targets` (
        `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_property`      INT UNSIGNED NOT NULL DEFAULT 1 UNIQUE,
        `target_occupancy` DECIMAL(5,2) DEFAULT 75.00,
        `target_adr`       DECIMAL(10,2) DEFAULT 0.00,
        `target_revpar`    DECIMAL(10,2) DEFAULT 0.00,
        `target_nps`       DECIMAL(5,2) DEFAULT 72.00,
        `budget_rooms`     DECIMAL(14,2) DEFAULT 0.00,
        `budget_fnb`       DECIMAL(14,2) DEFAULT 0.00,
        `budget_total`     DECIMAL(14,2) DEFAULT 0.00,
        `updated_at`       DATETIME ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    /* API keys para integraciones externas */
    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_api_key` (
        `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_property`  INT UNSIGNED NOT NULL DEFAULT 1,
        `name`         VARCHAR(100) NOT NULL,
        `api_key`      VARCHAR(64) NOT NULL COMMENT "SHA-256 del token real",
        `permissions`  JSON COMMENT "Array de endpoints permitidos",
        `last_used_at` DATETIME NULL,
        `expires_at`   DATETIME NULL,
        `active`       TINYINT(1) DEFAULT 1,
        `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uk_key` (`api_key`),
        INDEX `idx_active` (`active`, `expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    /* Log de llamadas API */
    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_api_log` (
        `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `api_key_hash`  VARCHAR(16),
        `method`        VARCHAR(10),
        `path`          VARCHAR(200),
        `status_code`   SMALLINT UNSIGNED,
        `ip_address`    VARCHAR(45),
        `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    /* Cargos a habitación (usado por F&B, Spa, etc.) */
    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_room_charge` (
        `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_booking`    INT UNSIGNED NOT NULL,
        `charge_type`   VARCHAR(20) NOT NULL COMMENT "FB, SPA, MINIBAR, LAUNDRY, OTHER",
        `description`   VARCHAR(300) NOT NULL,
        `amount`        DECIMAL(10,2) NOT NULL,
        `id_reference`  INT UNSIGNED NULL COMMENT "ID de la orden, reserva spa, etc.",
        `voided`        TINYINT(1) DEFAULT 0,
        `voided_by`     INT UNSIGNED NULL,
        `voided_at`     DATETIME NULL,
        `charged_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_booking`  (`id_booking`),
        INDEX `idx_type`     (`charge_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
];

foreach ($queries as $sql) {
    if (!Db::getInstance()->execute($sql)) { return false; }
}
return true;
