<?php
/**
 * SmartHotelOS — Módulo Smart Maintenance
 * Grupo Smart de Administración
 *
 * Mantenimiento preventivo y correctivo: órdenes de trabajo
 * digitales, SLA de respuesta, historial por activo y alertas.
 *
 * @module  smart_maintenance
 * @version 1.0.0
 */

if (!defined('_PS_VERSION_')) { exit; }

class Smart_Maintenance extends Module
{
    const MODULE_VERSION = '1.0.0';

    const TICKET_TYPES = [
        'CORRECTIVE'  => 'Correctivo (falla)',
        'PREVENTIVE'  => 'Preventivo (programado)',
        'INSPECTION'  => 'Inspección',
        'IMPROVEMENT' => 'Mejora',
        'EMERGENCY'   => 'Emergencia',
    ];

    const PRIORITIES = [
        1 => 'Baja',
        2 => 'Normal',
        3 => 'Alta',
        4 => 'Urgente',
        5 => 'Emergencia',
    ];

    const SLA_RESPONSE_MINUTES = [
        5 => 10,    // Emergencia: 10 min
        4 => 30,    // Urgente: 30 min
        3 => 120,   // Alta: 2 horas
        2 => 480,   // Normal: 8 horas
        1 => 1440,  // Baja: 24 horas
    ];

    const ASSET_TYPES = [
        'ROOM'      => 'Habitación',
        'HVAC'      => 'Climatización',
        'PLUMBING'  => 'Plomería',
        'ELECTRICAL'=> 'Eléctrico',
        'ELEVATOR'  => 'Ascensor',
        'POOL'      => 'Piscina',
        'GYM'       => 'Gimnasio',
        'KITCHEN'   => 'Cocina',
        'COMMON'    => 'Áreas comunes',
        'EXTERIOR'  => 'Exterior / Jardines',
        'IT'        => 'Tecnología / IT',
    ];

    public function __construct()
    {
        $this->name      = 'smart_maintenance';
        $this->tab       = 'administration';
        $this->version   = self::MODULE_VERSION;
        $this->author    = 'Smart Hotel Group';
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('Smart Maintenance');
        $this->description = $this->l(
            'Mantenimiento preventivo y correctivo: órdenes de trabajo, SLA de respuesta, ' .
            'historial por activo, checklist de inspección y alertas.'
        );
    }

    public function install(): bool
    {
        return parent::install()
            && $this->installSql()
            && $this->installTabs()
            && $this->registerHook([
                'displaySmartDashboardWidgets',
                'displaySmartFrontDeskAlerts',
                'actionSmartHousekeepingDefect',  // cuando HK reporta un defecto
            ])
            && $this->installDefaultConfig();
    }

    public function uninstall(): bool
    {
        return parent::uninstall() && $this->uninstallSql() && $this->uninstallTabs();
    }

    private function installSql(): bool
    {
        $queries = [

            // Activos del hotel (habitaciones, equipos, instalaciones)
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_asset` (
                `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `id_property`    INT UNSIGNED NOT NULL DEFAULT 1,
                `asset_code`     VARCHAR(30) NOT NULL,
                `name`           VARCHAR(200) NOT NULL,
                `asset_type`     VARCHAR(30) NOT NULL DEFAULT "ROOM",
                `id_room`        INT UNSIGNED NULL COMMENT "Si aplica",
                `location`       VARCHAR(200),
                `brand`          VARCHAR(100),
                `model`          VARCHAR(100),
                `serial_number`  VARCHAR(100),
                `purchase_date`  DATE NULL,
                `warranty_until` DATE NULL,
                `last_service`   DATE NULL,
                `next_service`   DATE NULL COMMENT "Próximo mantenimiento preventivo",
                `service_freq_days` SMALLINT UNSIGNED DEFAULT 90,
                `status`         ENUM("operational","maintenance","oos","disposed") DEFAULT "operational",
                `notes`          TEXT,
                `active`         TINYINT(1) DEFAULT 1,
                UNIQUE KEY `uk_property_code` (`id_property`, `asset_code`),
                INDEX `idx_next_service` (`next_service`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            // Tickets de mantenimiento
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_maint_ticket` (
                `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `id_property`     INT UNSIGNED NOT NULL DEFAULT 1,
                `ticket_number`   VARCHAR(20) NOT NULL UNIQUE,
                `ticket_type`     VARCHAR(20) NOT NULL DEFAULT "CORRECTIVE",
                `id_asset`        INT UNSIGNED NULL,
                `id_room`         INT UNSIGNED NULL,
                `id_booking`      INT UNSIGNED NULL COMMENT "Si fue reportado por un huésped",
                `title`           VARCHAR(300) NOT NULL,
                `description`     TEXT,
                `priority`        TINYINT UNSIGNED DEFAULT 2,
                `status`          ENUM("open","assigned","in_progress","pending_parts",
                                       "completed","verified","closed","cancelled")
                                  DEFAULT "open",
                `id_reported_by`  INT UNSIGNED NULL COMMENT "Empleado que reportó",
                `id_assigned_to`  INT UNSIGNED NULL COMMENT "Técnico asignado",
                `id_verified_by`  INT UNSIGNED NULL,
                `photo_urls`      JSON,
                `parts_needed`    TEXT,
                `parts_cost`      DECIMAL(10,2) DEFAULT 0.00,
                `labor_hours`     DECIMAL(5,2) DEFAULT 0.00,
                `resolution_notes' TEXT,
                `sla_deadline`    DATETIME NULL COMMENT "Calculado automáticamente por prioridad",
                `sla_breached`    TINYINT(1) DEFAULT 0,
                `opened_at`       DATETIME DEFAULT CURRENT_TIMESTAMP,
                `assigned_at`     DATETIME NULL,
                `started_at`      DATETIME NULL,
                `completed_at`    DATETIME NULL,
                `verified_at`     DATETIME NULL,
                `closed_at`       DATETIME NULL,
                INDEX `idx_status_priority` (`status`, `priority`),
                INDEX `idx_room`            (`id_room`),
                INDEX `idx_asset`           (`id_asset`),
                INDEX `idx_sla`             (`sla_deadline`, `sla_breached`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            // Comentarios / actualizaciones del ticket
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_maint_comment` (
                `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `id_ticket`    INT UNSIGNED NOT NULL,
                `id_employee`  INT UNSIGNED NOT NULL,
                `comment`      TEXT NOT NULL,
                `photo_urls`   JSON,
                `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_ticket` (`id_ticket`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            // Plan de mantenimiento preventivo
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_maint_plan` (
                `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `id_property`     INT UNSIGNED NOT NULL DEFAULT 1,
                `name`            VARCHAR(200) NOT NULL,
                `id_asset_type`   VARCHAR(30),
                `frequency_days`  SMALLINT UNSIGNED NOT NULL DEFAULT 90,
                `checklist`       JSON COMMENT "Lista de verificación del mantenimiento",
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
    }

    private function installTabs(): bool
    {
        foreach ([
            ['AdminSmartMaintenance',  'Mantenimiento',          'AdminSmartHotel'],
            ['AdminSmartMaintTickets', 'Tickets Activos',         'AdminSmartMaintenance'],
            ['AdminSmartMaintPlan',    'Plan Preventivo',         'AdminSmartMaintenance'],
            ['AdminSmartMaintAssets',  'Activos del Hotel',       'AdminSmartMaintenance'],
            ['AdminSmartMaintReports', 'Reportes Mantenimiento',  'AdminSmartMaintenance'],
        ] as [$cls, $name, $parent]) {
            $tab = new Tab();
            $tab->active     = 1;
            $tab->class_name = $cls;
            $tab->module     = $this->name;
            $tab->id_parent  = (int) Tab::getIdFromClassName($parent);
            foreach (Language::getLanguages() as $l) { $tab->name[$l['id_lang']] = $name; }
            $tab->add();
        }
        return true;
    }

    private function installDefaultConfig(): bool
    {
        foreach ([
            'SMART_MAINT_NOTIFY_URGENCY'   => 4,   // Notificar al manager si prioridad >= 4
            'SMART_MAINT_AUTO_OOS'         => 1,   // Poner habitación OOS si ticket urgente
            'SMART_MAINT_SLA_ALERT_BEFORE' => 30,  // Minutos antes de vencer SLA para alertar
        ] as $k => $v) {
            Configuration::updateValue($k, $v);
        }
        return true;
    }

    private function uninstallSql(): bool
    {
        foreach (['smart_asset','smart_maint_ticket','smart_maint_comment','smart_maint_plan'] as $t) {
            Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . pSQL($t) . '`');
        }
        return true;
    }

    private function uninstallTabs(): bool
    {
        foreach (['AdminSmartMaintenance','AdminSmartMaintTickets','AdminSmartMaintPlan',
                  'AdminSmartMaintAssets','AdminSmartMaintReports'] as $c) {
            $id = (int) Tab::getIdFromClassName($c);
            if ($id) { (new Tab($id))->delete(); }
        }
        return true;
    }

    // ──────────────────────────────────────────────────────────
    //  LÓGICA DE NEGOCIO
    // ──────────────────────────────────────────────────────────

    /**
     * Crear ticket de mantenimiento.
     * Puede ser creado por cualquier departamento (HK, FD, F&B, etc.)
     */
    public function createTicket(array $data): array
    {
        $priority   = (int) ($data['priority'] ?? 2);
        $slaMinutes = self::SLA_RESPONSE_MINUTES[$priority] ?? 480;
        $slaDeadline = date('Y-m-d H:i:s', time() + $slaMinutes * 60);
        $ticketNum  = $this->generateTicketNumber();

        Db::getInstance()->insert('smart_maint_ticket', [
            'id_property'    => (int) ($data['id_property'] ?? 1),
            'ticket_number'  => pSQL($ticketNum),
            'ticket_type'    => pSQL($data['type'] ?? 'CORRECTIVE'),
            'id_asset'       => isset($data['id_asset'])  ? (int) $data['id_asset']  : null,
            'id_room'        => isset($data['id_room'])   ? (int) $data['id_room']   : null,
            'id_booking'     => isset($data['id_booking'])? (int) $data['id_booking']: null,
            'title'          => pSQL($data['title']),
            'description'    => pSQL($data['description'] ?? ''),
            'priority'       => $priority,
            'status'         => 'open',
            'id_reported_by' => isset($data['id_reporter']) ? (int) $data['id_reporter'] : null,
            'photo_urls'     => pSQL(json_encode($data['photos'] ?? [])),
            'sla_deadline'   => pSQL($slaDeadline),
        ]);

        $idTicket = (int) Db::getInstance()->Insert_ID();

        // Si prioridad alta/urgente, poner habitación fuera de servicio automáticamente
        if ($priority >= 4 && !empty($data['id_room'])
            && (int) Configuration::get('SMART_MAINT_AUTO_OOS')) {
            $this->setRoomOOS((int) $data['id_room'], $ticketNum);
        }

        // Notificar al jefe de mantenimiento si prioridad >= umbral configurado
        if ($priority >= (int) Configuration::get('SMART_MAINT_NOTIFY_URGENCY')) {
            Hook::exec('actionSmartSendNotification', [
                'type'     => 'maintenance_urgent',
                'ticket'   => $ticketNum,
                'priority' => $priority,
                'title'    => $data['title'],
            ]);
        }

        return [
            'success'       => true,
            'id_ticket'     => $idTicket,
            'ticket_number' => $ticketNum,
            'sla_deadline'  => $slaDeadline,
        ];
    }

    /**
     * Asignar ticket a un técnico.
     */
    public function assignTicket(int $idTicket, int $idTechnician): bool
    {
        return Db::getInstance()->update('smart_maint_ticket', [
            'id_assigned_to' => $idTechnician,
            'status'         => 'assigned',
            'assigned_at'    => date('Y-m-d H:i:s'),
        ], 'id = ' . (int) $idTicket . ' AND status = "open"');
    }

    /**
     * Completar ticket de mantenimiento.
     */
    public function completeTicket(int $idTicket, array $data): array
    {
        $ticket = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'smart_maint_ticket` WHERE id = ' . (int) $idTicket
        );
        if (!$ticket) { return ['success' => false, 'message' => 'Ticket no encontrado']; }

        Db::getInstance()->update('smart_maint_ticket', [
            'status'           => 'completed',
            'resolution_notes' => pSQL($data['resolution'] ?? ''),
            'parts_cost'       => (float) ($data['parts_cost'] ?? 0),
            'labor_hours'      => (float) ($data['labor_hours'] ?? 0),
            'completed_at'     => date('Y-m-d H:i:s'),
        ], 'id = ' . (int) $idTicket);

        // Si había habitación OOS, restaurar
        if ($ticket['id_room']) {
            Db::getInstance()->update('smart_hk_room_status',
                ['status' => 'VD'],
                'id_room = ' . (int) $ticket['id_room'] . ' AND status = "OO"'
            );
        }

        return ['success' => true, 'ticket_number' => $ticket['ticket_number']];
    }

    /**
     * Verificar SLAs y generar alertas por incumplimiento.
     */
    public function checkSlaBreaches(): int
    {
        $breached = Db::getInstance()->executeS(
            'SELECT id, ticket_number, priority, title
             FROM `' . _DB_PREFIX_ . 'smart_maint_ticket`
             WHERE status NOT IN ("completed","verified","closed","cancelled")
             AND sla_deadline < NOW() AND sla_breached = 0'
        ) ?: [];

        foreach ($breached as $ticket) {
            Db::getInstance()->update('smart_maint_ticket',
                ['sla_breached' => 1],
                'id = ' . (int) $ticket['id']
            );
            Hook::exec('actionSmartSendNotification', [
                'type'   => 'maintenance_sla_breach',
                'ticket' => $ticket['ticket_number'],
                'title'  => $ticket['title'],
            ]);
        }

        return count($breached);
    }

    /**
     * KPIs de mantenimiento para el dashboard.
     */
    public function getKpis(int $idProperty): array
    {
        $db = Db::getInstance();
        return [
            'open_tickets'    => (int) $db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'smart_maint_ticket` WHERE status = "open" AND id_property = ' . $idProperty),
            'in_progress'     => (int) $db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'smart_maint_ticket` WHERE status IN ("assigned","in_progress") AND id_property = ' . $idProperty),
            'sla_breached'    => (int) $db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'smart_maint_ticket` WHERE sla_breached = 1 AND status NOT IN ("closed","cancelled") AND id_property = ' . $idProperty),
            'rooms_oos'       => (int) $db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'smart_hk_room_status` WHERE status = "OO"'),
            'completed_today' => (int) $db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'smart_maint_ticket` WHERE DATE(completed_at) = "' . date('Y-m-d') . '" AND id_property = ' . $idProperty),
        ];
    }

    private function setRoomOOS(int $idRoom, string $reason): void
    {
        Db::getInstance()->update('smart_hk_room_status',
            ['status' => 'OO', 'updated_at' => date('Y-m-d H:i:s')],
            'id_room = ' . (int) $idRoom
        );
    }

    private function generateTicketNumber(): string
    {
        $prefix = 'MNT-' . date('Ym') . '-';
        $last   = Db::getInstance()->getValue(
            'SELECT MAX(CAST(SUBSTRING(ticket_number, ' . (strlen($prefix) + 1) . ') AS UNSIGNED))
             FROM `' . _DB_PREFIX_ . 'smart_maint_ticket`
             WHERE ticket_number LIKE "' . pSQL($prefix) . '%"'
        );
        return $prefix . str_pad(((int) $last) + 1, 4, '0', STR_PAD_LEFT);
    }

    public function getContent(): string { return $this->display(__FILE__, 'views/templates/admin/configuration.tpl'); }
}
