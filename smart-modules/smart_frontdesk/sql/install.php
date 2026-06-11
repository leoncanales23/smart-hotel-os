<?php
/**
 * SQL de instalación — smart_frontdesk
 * Grupo Smart de Administración
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

$queries = [

    // Log de check-in
    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_checkin_log` (
        `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_booking`    INT UNSIGNED NOT NULL,
        `id_room`       INT UNSIGNED NOT NULL,
        `id_agent`      INT UNSIGNED NOT NULL,
        `checkin_at`    DATETIME NOT NULL,
        `ip_address`    VARCHAR(45),
        `device_type`   ENUM("desktop","kiosk","mobile","api") DEFAULT "desktop",
        INDEX `idx_booking` (`id_booking`),
        INDEX `idx_checkin_date` (`checkin_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    // Log de check-out
    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_checkout_log` (
        `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_booking`    INT UNSIGNED NOT NULL,
        `id_agent`      INT UNSIGNED NOT NULL,
        `checkout_at`   DATETIME NOT NULL,
        `ip_address`    VARCHAR(45),
        `folio_total`   DECIMAL(10,2) DEFAULT 0.00,
        `payment_method` VARCHAR(50),
        INDEX `idx_booking` (`id_booking`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    // Asignación de habitaciones
    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_room_assignment` (
        `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_booking`    INT UNSIGNED NOT NULL,
        `id_room`       INT UNSIGNED NOT NULL,
        `assigned_at`   DATETIME NOT NULL,
        `assigned_by`   INT UNSIGNED,
        `released_at`   DATETIME NULL,
        `active`        TINYINT(1) DEFAULT 1,
        UNIQUE KEY `uk_booking_room` (`id_booking`, `id_room`),
        INDEX `idx_room_active` (`id_room`, `active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    // Documentos de identidad (scaneados en check-in)
    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_guest_document` (
        `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_booking`    INT UNSIGNED NOT NULL,
        `id_customer`   INT UNSIGNED,
        `id_type`       VARCHAR(20) NOT NULL,
        `id_number`     VARCHAR(50) NOT NULL,
        `id_country`    CHAR(2),
        `id_expiry`     DATE NULL,
        `scanned_at`    DATETIME NOT NULL,
        INDEX `idx_booking` (`id_booking`),
        INDEX `idx_customer` (`id_customer`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    // Llaves digitales / códigos de acceso
    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_digital_key` (
        `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_booking`    INT UNSIGNED NOT NULL,
        `id_room`       INT UNSIGNED NOT NULL,
        `key_code`      VARCHAR(255) NOT NULL,
        `key_type`      ENUM("pin","qr","nfc","mobile_key") DEFAULT "pin",
        `valid_from`    DATETIME NOT NULL,
        `valid_until`   DATETIME NOT NULL,
        `issued_at`     DATETIME NOT NULL,
        `revoked_at`    DATETIME NULL,
        `active`        TINYINT(1) DEFAULT 1,
        INDEX `idx_booking` (`id_booking`),
        INDEX `idx_room` (`id_room`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    // Notas de recepción (para comunicación entre turnos)
    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_fd_note` (
        `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_booking`    INT UNSIGNED,
        `id_agent`      INT UNSIGNED NOT NULL,
        `note_type`     ENUM("general","vip","complaint","request","maintenance") DEFAULT "general",
        `note`          TEXT NOT NULL,
        `priority`      ENUM("low","normal","high","urgent") DEFAULT "normal",
        `resolved`      TINYINT(1) DEFAULT 0,
        `resolved_by`   INT UNSIGNED NULL,
        `resolved_at`   DATETIME NULL,
        `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_booking` (`id_booking`),
        INDEX `idx_priority` (`priority`, `resolved`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

];

foreach ($queries as $query) {
    if (!Db::getInstance()->execute($query)) {
        return false;
    }
}

return true;
