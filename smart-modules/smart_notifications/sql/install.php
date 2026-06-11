<?php
/**
 * SQL de instalación — smart_notifications
 * Grupo Smart de Administración
 */
if (!defined('_PS_VERSION_')) { exit; }

$queries = [
    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_notification_queue` (
        `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_property`   INT UNSIGNED NOT NULL DEFAULT 1,
        `channel`       ENUM("WHATSAPP","EMAIL","PUSH","INTERNAL","SMS") NOT NULL,
        `template`      VARCHAR(100) NOT NULL,
        `recipient`     VARCHAR(200) NOT NULL,
        `data`          JSON,
        `status`        ENUM("pending","sending","sent","failed","skipped") DEFAULT "pending",
        `attempts`      TINYINT UNSIGNED DEFAULT 0,
        `error_msg`     TEXT NULL,
        `scheduled_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
        `sent_at`       DATETIME NULL,
        `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_status_scheduled` (`status`, `scheduled_at`),
        INDEX `idx_channel`          (`channel`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_notification_log` (
        `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `id_queue`     INT UNSIGNED NULL,
        `channel`      VARCHAR(20),
        `template`     VARCHAR(100),
        `recipient`    VARCHAR(200),
        `status`       VARCHAR(20),
        `provider_ref` VARCHAR(200),
        `sent_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_sent`    (`sent_at`),
        INDEX `idx_channel` (`channel`, `sent_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
];

foreach ($queries as $sql) {
    if (!Db::getInstance()->execute($sql)) { return false; }
}
return true;
