<?php
/**
 * SQL de instalación — smart_concierge
 * Grupo Smart de Administración
 */
if (!defined('_PS_VERSION_')) { exit; }

$queries = [
    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_concierge_conversation` (
        `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_property`     INT UNSIGNED NOT NULL DEFAULT 1,
        `wa_phone`        VARCHAR(20) NOT NULL,
        `id_customer`     INT UNSIGNED NULL,
        `id_booking`      INT UNSIGNED NULL,
        `room_number`     VARCHAR(10) NULL,
        `language`        VARCHAR(5) DEFAULT "es",
        `status`          ENUM("bot","escalated","closed") DEFAULT "bot",
        `id_agent`        INT UNSIGNED NULL,
        `context`         JSON,
        `last_message_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uk_phone_booking` (`wa_phone`, `id_booking`),
        INDEX `idx_status` (`status`),
        INDEX `idx_phone`  (`wa_phone`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_concierge_message` (
        `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_conversation` INT UNSIGNED NOT NULL,
        `direction`       ENUM("inbound","outbound") NOT NULL,
        `message_type`    ENUM("text","image","audio","document","template") DEFAULT "text",
        `content`         TEXT NOT NULL,
        `wa_message_id`   VARCHAR(100) NULL,
        `intent`          VARCHAR(50) NULL,
        `confidence`      DECIMAL(4,3) NULL,
        `handled_by`      ENUM("bot","agent") DEFAULT "bot",
        `id_agent`        INT UNSIGNED NULL,
        `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_conversation` (`id_conversation`),
        INDEX `idx_created`      (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_concierge_request` (
        `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_conversation` INT UNSIGNED NOT NULL,
        `id_property`     INT UNSIGNED NOT NULL DEFAULT 1,
        `id_booking`      INT UNSIGNED NULL,
        `request_type`    VARCHAR(50) NOT NULL,
        `description`     TEXT,
        `status`          ENUM("open","in_progress","completed","cancelled") DEFAULT "open",
        `id_assigned_to`  INT UNSIGNED NULL,
        `resolution`      TEXT,
        `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
        `resolved_at`     DATETIME NULL,
        INDEX `idx_booking` (`id_booking`),
        INDEX `idx_status`  (`status`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
];

foreach ($queries as $sql) {
    if (!Db::getInstance()->execute($sql)) { return false; }
}
return true;
