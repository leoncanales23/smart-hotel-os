<?php
/**
 * SQL de instalación — smart_housekeeping
 * Grupo Smart de Administración
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

$queries = [

    // Estado de habitaciones (tabla maestra de estado en tiempo real)
    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_hk_room_status` (
        `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_room`       INT UNSIGNED NOT NULL UNIQUE,
        `id_property`   INT UNSIGNED NOT NULL DEFAULT 1,
        `status`        VARCHAR(5) NOT NULL DEFAULT "VD",
        `status_since`  DATETIME,
        `id_staff`      INT UNSIGNED NULL COMMENT "Camarera actualmente en la habitación",
        `updated_at`    DATETIME ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_status` (`status`),
        INDEX `idx_property_status` (`id_property`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    -- Tareas de housekeeping
    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_hk_task` (
        `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_property`       INT UNSIGNED NOT NULL DEFAULT 1,
        `id_room`           INT UNSIGNED NOT NULL,
        `id_booking`        INT UNSIGNED NULL,
        `id_staff`          INT UNSIGNED NULL,
        `task_type`         VARCHAR(30) NOT NULL,
        `priority`          TINYINT UNSIGNED DEFAULT 2,
        `status`            ENUM("PENDING","ASSIGNED","IN_PROGRESS","COMPLETED","CANCELLED","SKIPPED")
                            DEFAULT "PENDING",
        `notes`             TEXT,
        `completion_notes`  TEXT,
        `scheduled_for`     DATE NOT NULL,
        `assigned_at`       DATETIME NULL,
        `started_at`        DATETIME NULL,
        `completed_at`      DATETIME NULL,
        `duration_minutes`  SMALLINT UNSIGNED NULL,
        `created_at`        DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_room_date`     (`id_room`, `scheduled_for`),
        INDEX `idx_staff_date`    (`id_staff`, `scheduled_for`),
        INDEX `idx_status_date`   (`status`, `scheduled_for`),
        INDEX `idx_property_date` (`id_property`, `scheduled_for`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    -- Staff de housekeeping
    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_hk_staff` (
        `id_staff`      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_property`   INT UNSIGNED NOT NULL DEFAULT 1,
        `id_employee`   INT UNSIGNED NULL,
        `firstname`     VARCHAR(100) NOT NULL,
        `lastname`      VARCHAR(100) NOT NULL,
        `phone`         VARCHAR(20),
        `role`          ENUM("housekeeper","supervisor","inspector","manager") DEFAULT "housekeeper",
        `floor_assign`  VARCHAR(50) COMMENT "Pisos asignados, ej: 3,4,5",
        `active`        TINYINT(1) DEFAULT 1,
        `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_property_role` (`id_property`, `role`, `active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    -- Inspecciones de habitaciones
    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_hk_inspection` (
        `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_room`           INT UNSIGNED NOT NULL,
        `id_task`           INT UNSIGNED NULL,
        `id_inspector`      INT UNSIGNED NOT NULL,
        `result`            ENUM("APPROVED","REJECTED","NEEDS_TOUCH_UP") NOT NULL,
        `score`             TINYINT UNSIGNED COMMENT "Puntaje 1-100",
        `checklist`         JSON COMMENT "Checklist detallada de la inspección",
        `notes`             TEXT,
        `photo_urls`        JSON COMMENT "URLs de fotos adjuntas",
        `inspected_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_room`     (`id_room`),
        INDEX `idx_date`     (`inspected_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    -- Objetos perdidos y encontrados
    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_hk_lost_found` (
        `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_property`   INT UNSIGNED NOT NULL DEFAULT 1,
        `id_room`       INT UNSIGNED NULL,
        `id_booking`    INT UNSIGNED NULL,
        `id_staff`      INT UNSIGNED NOT NULL COMMENT "Quien lo encontró",
        `description`   VARCHAR(500) NOT NULL,
        `category`      VARCHAR(50) DEFAULT "OTHER",
        `location`      VARCHAR(200),
        `storage_loc`   VARCHAR(200) COMMENT "Dónde está guardado",
        `found_at`      DATETIME NOT NULL,
        `status`        ENUM("STORED","CLAIMED","DONATED","DISPOSED") DEFAULT "STORED",
        `claimed_by`    VARCHAR(200) NULL,
        `claimed_at`    DATETIME NULL,
        `photo_url`     VARCHAR(500) NULL,
        `notes`         TEXT,
        INDEX `idx_status`  (`status`),
        INDEX `idx_found`   (`found_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    -- Suministros y amenidades por habitación (control de inventario)
    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_hk_room_supplies` (
        `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_room`       INT UNSIGNED NOT NULL,
        `id_task`       INT UNSIGNED NULL,
        `supply_type`   VARCHAR(50) NOT NULL,
        `quantity`      TINYINT UNSIGNED NOT NULL DEFAULT 1,
        `recorded_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_room` (`id_room`),
        INDEX `idx_type` (`supply_type`, `recorded_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

];

foreach ($queries as $query) {
    if (!Db::getInstance()->execute($query)) {
        return false;
    }
}

return true;
