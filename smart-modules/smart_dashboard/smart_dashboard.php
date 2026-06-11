<?php
/**
 * SmartHotelOS — Módulo Smart Dashboard Ejecutivo
 * Grupo Smart de Administración
 *
 * KPIs en tiempo real para dirección: ocupación, RevPAR, ADR,
 * ingresos por departamento, NPS y alertas operacionales.
 * Soporte multi-propiedad para todo el Grupo Smart.
 *
 * @module      smart_dashboard
 * @version     1.0.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Smart_Dashboard extends Module
{
    const MODULE_VERSION = '1.0.0';

    // ── KPI Thresholds (alertas) ──────────────────────────
    const OCCUPANCY_ALERT_LOW  = 40;   // % ocupación crítica
    const OCCUPANCY_ALERT_HIGH = 95;   // % riesgo overbooking
    const REVPAR_TARGET_BASE   = 0;    // Se configura por propiedad
    const CHECKIN_DELAY_ALERT  = 30;   // minutos de retraso en check-in
    const HK_PENDING_ALERT     = 10;   // tareas housekeeping pendientes

    public function __construct()
    {
        $this->name      = 'smart_dashboard';
        $this->tab       = 'administration';
        $this->version   = self::MODULE_VERSION;
        $this->author    = 'Smart Hotel Group';
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('Smart Dashboard Ejecutivo');
        $this->description = $this->l(
            'KPIs hoteleros en tiempo real: ocupación, RevPAR, ADR, ingresos por departamento, ' .
            'NPS y alertas operacionales. Estándar Forbes 5 estrellas.'
        );
    }

    public function install(): bool
    {
        return parent::install()
            && $this->installSql()
            && $this->installTab()
            && $this->registerHook(['displaySmartDashboardWidgets', 'actionSmartCheckIn',
                                    'actionSmartCheckOut', 'actionSmartBookingCreated'])
            && $this->installDefaultConfig();
    }

    public function uninstall(): bool
    {
        return parent::uninstall() && $this->uninstallSql() && $this->uninstallTab();
    }

    private function installSql(): bool
    {
        $queries = [
            // Snapshot diario de KPIs (para histórico y comparativas)
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

            // Alertas operacionales
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

            // Config de KPIs objetivo por propiedad
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_dashboard_targets` (
                `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `id_property`     INT UNSIGNED NOT NULL DEFAULT 1 UNIQUE,
                `target_occupancy` DECIMAL(5,2) DEFAULT 75.00,
                `target_adr`      DECIMAL(10,2) DEFAULT 0.00,
                `target_revpar`   DECIMAL(10,2) DEFAULT 0.00,
                `target_nps`      DECIMAL(5,2) DEFAULT 72.00,
                `budget_rooms`    DECIMAL(14,2) DEFAULT 0.00,
                `budget_fnb`      DECIMAL(14,2) DEFAULT 0.00,
                `budget_total`    DECIMAL(14,2) DEFAULT 0.00,
                `updated_at`      DATETIME ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        ];

        foreach ($queries as $sql) {
            if (!Db::getInstance()->execute($sql)) {
                return false;
            }
        }
        return true;
    }

    private function installTab(): bool
    {
        foreach ([
            ['AdminSmartDashboard',      'Dashboard Ejecutivo',   'AdminSmartHotel'],
            ['AdminSmartDashboardKpis',  'KPIs en Tiempo Real',   'AdminSmartDashboard'],
            ['AdminSmartDashboardAlerts','Alertas Operacionales', 'AdminSmartDashboard'],
            ['AdminSmartDashboardReports','Reportes',             'AdminSmartDashboard'],
        ] as [$cls, $name, $parent]) {
            $tab = new Tab();
            $tab->active     = 1;
            $tab->class_name = $cls;
            $tab->module     = $this->name;
            $tab->id_parent  = (int) Tab::getIdFromClassName($parent);
            foreach (Language::getLanguages() as $l) {
                $tab->name[$l['id_lang']] = $name;
            }
            $tab->add();
        }
        return true;
    }

    private function installDefaultConfig(): bool
    {
        foreach ([
            'SMART_DASH_REFRESH_INTERVAL' => 30,    // segundos
            'SMART_DASH_SHOW_BUDGET'      => 1,
            'SMART_DASH_SHOW_YOY'         => 1,      // año anterior
            'SMART_DASH_SHOW_FORECAST'    => 1,
            'SMART_DASH_ALERT_EMAIL'      => '',
            'SMART_DASH_ALERT_WHATSAPP'   => '',
        ] as $k => $v) {
            Configuration::updateValue($k, $v);
        }
        return true;
    }

    private function uninstallSql(): bool
    {
        foreach (['smart_dashboard_snapshot', 'smart_dashboard_alert', 'smart_dashboard_targets'] as $t) {
            Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . pSQL($t) . '`');
        }
        return true;
    }

    private function uninstallTab(): bool
    {
        foreach (['AdminSmartDashboard','AdminSmartDashboardKpis','AdminSmartDashboardAlerts','AdminSmartDashboardReports'] as $c) {
            $id = (int) Tab::getIdFromClassName($c);
            if ($id) { (new Tab($id))->delete(); }
        }
        return true;
    }

    // ──────────────────────────────────────────────────────────
    //  API PÚBLICA — KPIs
    // ──────────────────────────────────────────────────────────

    /**
     * Obtener todos los KPIs del día actual para una propiedad.
     * Este es el método principal que consume el frontend Vue.js.
     *
     * @param int    $idProperty
     * @param string $date        (Y-m-d), por defecto hoy
     * @return array
     */
    public function getDailyKpis(int $idProperty = 1, string $date = ''): array
    {
        if (!$date) {
            $date = date('Y-m-d');
        }

        $occupancy  = $this->getOccupancyData($idProperty, $date);
        $revenue    = $this->getRevenueData($idProperty, $date);
        $operations = $this->getOperationsData($idProperty, $date);
        $targets    = $this->getTargets($idProperty);
        $yoy        = $this->getYearOverYear($idProperty, $date);

        return [
            'date'       => $date,
            'property'   => $idProperty,
            'generated'  => date('Y-m-d H:i:s'),
            'occupancy'  => $occupancy,
            'revenue'    => $revenue,
            'operations' => $operations,
            'targets'    => $targets,
            'yoy'        => $yoy,
            'alerts'     => $this->getActiveAlerts($idProperty),
        ];
    }

    /**
     * Datos de ocupación del día.
     */
    public function getOccupancyData(int $idProperty, string $date): array
    {
        $db = Db::getInstance();

        $totalRooms = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'htl_room_info`
             WHERE id_hotel = ' . (int) $idProperty . ' AND is_active = 1'
        );

        $occupied = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'htl_booking_detail`
             WHERE booking_status IN (2,6)
             AND DATE(booking_date_from) <= "' . pSQL($date) . '"
             AND DATE(booking_date_to) > "' . pSQL($date) . '"'
        );

        $arrivals = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'htl_booking_detail`
             WHERE DATE(booking_date_from) = "' . pSQL($date) . '"
             AND booking_status IN (1,2)'
        );

        $departures = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'htl_booking_detail`
             WHERE DATE(booking_date_to) = "' . pSQL($date) . '"
             AND booking_status IN (2,3,6)'
        );

        $checkedIn = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'htl_booking_detail`
             WHERE DATE(booking_date_from) = "' . pSQL($date) . '"
             AND booking_status = 2'
        );

        $noShows = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'htl_booking_detail`
             WHERE DATE(booking_date_from) = "' . pSQL($date) . '"
             AND booking_status = 5'
        );

        $occupancyPct = $totalRooms > 0
            ? round(($occupied / $totalRooms) * 100, 1)
            : 0;

        return [
            'total_rooms'      => $totalRooms,
            'occupied'         => $occupied,
            'available'        => $totalRooms - $occupied,
            'occupancy_pct'    => $occupancyPct,
            'arrivals_total'   => $arrivals,
            'arrivals_checked' => $checkedIn,
            'arrivals_pending' => $arrivals - $checkedIn,
            'departures'       => $departures,
            'in_house'         => $occupied,
            'no_shows'         => $noShows,
            'status'           => $this->getOccupancyStatus($occupancyPct),
        ];
    }

    /**
     * Datos de ingresos del día.
     */
    public function getRevenueData(int $idProperty, string $date): array
    {
        $db = Db::getInstance();

        // Revenue de habitaciones (reservas con check-in hoy o en casa)
        $roomsRevenue = (float) $db->getValue(
            'SELECT COALESCE(SUM(total_paid_amount), 0)
             FROM `' . _DB_PREFIX_ . 'htl_booking_detail`
             WHERE DATE(booking_date_from) <= "' . pSQL($date) . '"
             AND DATE(booking_date_to) > "' . pSQL($date) . '"
             AND booking_status IN (2,3,6)'
        ) / max(1, (int) $db->getValue(
            'SELECT COALESCE(DATEDIFF(booking_date_to, booking_date_from), 1)
             FROM `' . _DB_PREFIX_ . 'htl_booking_detail`
             WHERE booking_status IN (2,3,6) LIMIT 1'
        ));

        // Revenue total del día (habitaciones + extras)
        $totalRevenue = $roomsRevenue; // TODO: sumar F&B, spa, etc.

        // ADR — Average Daily Rate
        $occupiedRooms = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'htl_booking_detail`
             WHERE booking_status IN (2,6)
             AND DATE(booking_date_from) <= "' . pSQL($date) . '"
             AND DATE(booking_date_to) > "' . pSQL($date) . '"'
        );

        $adr = $occupiedRooms > 0 ? round($roomsRevenue / $occupiedRooms, 2) : 0;

        // RevPAR — Revenue Per Available Room
        $totalRooms = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'htl_room_info`
             WHERE id_hotel = ' . (int) $idProperty . ' AND is_active = 1'
        );
        $revpar = $totalRooms > 0 ? round($roomsRevenue / $totalRooms, 2) : 0;

        return [
            'total'         => round($totalRevenue, 2),
            'rooms'         => round($roomsRevenue, 2),
            'fnb'           => 0.00,    // TODO: módulo F&B
            'spa'           => 0.00,    // TODO: módulo Spa
            'other'         => 0.00,
            'adr'           => $adr,
            'revpar'        => $revpar,
            'trevpar'       => $totalRooms > 0 ? round($totalRevenue / $totalRooms, 2) : 0,
            'currency'      => Configuration::get('PS_CURRENCY_DEFAULT') ?: 'CLP',
        ];
    }

    /**
     * Datos operacionales del día.
     */
    public function getOperationsData(int $idProperty, string $date): array
    {
        $db = Db::getInstance();

        // Housekeeping
        $hkPending = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'smart_hk_task`
             WHERE status IN ("PENDING","ASSIGNED")
             AND DATE(scheduled_for) = "' . pSQL($date) . '"'
        );
        $hkDone = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'smart_hk_task`
             WHERE status = "COMPLETED"
             AND DATE(scheduled_for) = "' . pSQL($date) . '"'
        );
        $hkInProgress = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'smart_hk_task`
             WHERE status = "IN_PROGRESS"'
        );

        // Habitaciones limpias vs sucias
        $cleanRooms = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'smart_hk_room_status`
             WHERE status IN ("IN","CL","VC")'
        );
        $dirtyRooms = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'smart_hk_room_status`
             WHERE status IN ("DI","VD","OD")'
        );

        // Mantenimiento (si módulo activo)
        $maintenanceOpen = 0; // TODO: módulo mantenimiento

        // Alertas activas
        $activeAlerts = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'smart_dashboard_alert`
             WHERE id_property = ' . (int) $idProperty . '
             AND resolved = 0'
        );

        return [
            'housekeeping' => [
                'pending'     => $hkPending,
                'in_progress' => $hkInProgress,
                'completed'   => $hkDone,
                'total'       => $hkPending + $hkInProgress + $hkDone,
                'clean_rooms' => $cleanRooms,
                'dirty_rooms' => $dirtyRooms,
            ],
            'maintenance' => [
                'open_tickets' => $maintenanceOpen,
            ],
            'alerts' => [
                'total'    => $activeAlerts,
                'critical' => $this->countAlertsBySeverity($idProperty, 'critical'),
                'warning'  => $this->countAlertsBySeverity($idProperty, 'warning'),
            ],
        ];
    }

    /**
     * KPIs para el período (semana / mes / YTD).
     */
    public function getPeriodKpis(int $idProperty, string $from, string $to): array
    {
        $db   = Db::getInstance();
        $days = max(1, (int) $db->getValue(
            'SELECT DATEDIFF("' . pSQL($to) . '", "' . pSQL($from) . '") + 1'
        ));

        $revenue = (float) $db->getValue(
            'SELECT COALESCE(SUM(total_paid_amount), 0)
             FROM `' . _DB_PREFIX_ . 'htl_booking_detail`
             WHERE booking_status IN (2,3,6)
             AND DATE(booking_date_from) BETWEEN "' . pSQL($from) . '" AND "' . pSQL($to) . '"'
        );

        $bookings = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'htl_booking_detail`
             WHERE booking_status NOT IN (4)
             AND DATE(booking_date_from) BETWEEN "' . pSQL($from) . '" AND "' . pSQL($to) . '"'
        );

        $avgStay = (float) $db->getValue(
            'SELECT COALESCE(AVG(DATEDIFF(booking_date_to, booking_date_from)), 0)
             FROM `' . _DB_PREFIX_ . 'htl_booking_detail`
             WHERE booking_status IN (2,3,6)
             AND DATE(booking_date_from) BETWEEN "' . pSQL($from) . '" AND "' . pSQL($to) . '"'
        );

        // Get daily snapshots for chart data
        $snapshots = $db->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'smart_dashboard_snapshot`
             WHERE id_property = ' . (int) $idProperty . '
             AND snapshot_date BETWEEN "' . pSQL($from) . '" AND "' . pSQL($to) . '"
             ORDER BY snapshot_date ASC'
        ) ?: [];

        return [
            'period'           => ['from' => $from, 'to' => $to, 'days' => $days],
            'total_revenue'    => round($revenue, 2),
            'total_bookings'   => $bookings,
            'avg_daily_revenue'=> $days > 0 ? round($revenue / $days, 2) : 0,
            'avg_length_stay'  => round($avgStay, 1),
            'chart_data'       => $this->formatChartData($snapshots),
        ];
    }

    /**
     * Comparativa año vs año anterior (YoY).
     */
    public function getYearOverYear(int $idProperty, string $date): array
    {
        $lastYear = date('Y-m-d', strtotime($date . ' -1 year'));

        $thisYear = $this->getRevenueData($idProperty, $date);
        $prevYear = $this->getSnapshotData($idProperty, $lastYear);

        $revenueChange = 0;
        if ($prevYear && $prevYear['total_revenue'] > 0) {
            $revenueChange = round(
                (($thisYear['total'] - $prevYear['total_revenue']) / $prevYear['total_revenue']) * 100, 1
            );
        }

        $occupancyChange = 0;
        if ($prevYear && $prevYear['occupied_rooms'] > 0 && $prevYear['total_rooms'] > 0) {
            $prevOcc   = ($prevYear['occupied_rooms'] / $prevYear['total_rooms']) * 100;
            $thisOcc   = $this->getOccupancyData($idProperty, $date)['occupancy_pct'];
            $occupancyChange = round($thisOcc - $prevOcc, 1);
        }

        return [
            'revenue_change_pct'   => $revenueChange,
            'occupancy_change_pts' => $occupancyChange,
            'adr_change_pct'       => 0, // TODO: calcular con snapshot
            'comparison_date'      => $lastYear,
        ];
    }

    // ──────────────────────────────────────────────────────────
    //  ALERTAS
    // ──────────────────────────────────────────────────────────

    /**
     * Evaluar KPIs y generar alertas automáticas si se superan umbrales.
     * Ejecutar cada N minutos via cron.
     */
    public function evaluateAlerts(int $idProperty = 1): void
    {
        $date  = date('Y-m-d');
        $occ   = $this->getOccupancyData($idProperty, $date);
        $ops   = $this->getOperationsData($idProperty, $date);

        // Alerta: ocupación muy baja
        if ($occ['occupancy_pct'] < self::OCCUPANCY_ALERT_LOW && $occ['total_rooms'] > 0) {
            $this->createAlert($idProperty, 'LOW_OCCUPANCY', 'warning',
                'Ocupación crítica del ' . $occ['occupancy_pct'] . '%',
                'La ocupación del hotel está por debajo del ' . self::OCCUPANCY_ALERT_LOW . '% umbral mínimo.',
                ['occupancy' => $occ['occupancy_pct']]
            );
        }

        // Alerta: riesgo de overbooking
        if ($occ['occupancy_pct'] >= self::OCCUPANCY_ALERT_HIGH) {
            $this->createAlert($idProperty, 'OVERBOOKING_RISK', 'critical',
                'Riesgo de overbooking — ' . $occ['occupancy_pct'] . '% ocupación',
                'El hotel supera el ' . self::OCCUPANCY_ALERT_HIGH . '% de ocupación. Revisar reservas pendientes.',
                ['available' => $occ['available']]
            );
        }

        // Alerta: muchas llegadas sin check-in
        if ($occ['arrivals_pending'] > 5) {
            $this->createAlert($idProperty, 'PENDING_CHECKINS', 'warning',
                $occ['arrivals_pending'] . ' llegadas pendientes de check-in',
                'Hay ' . $occ['arrivals_pending'] . ' huéspedes con reserva para hoy que aún no han hecho check-in.',
                ['pending' => $occ['arrivals_pending']]
            );
        }

        // Alerta: housekeeping con muchas tareas atrasadas
        if ($ops['housekeeping']['pending'] > self::HK_PENDING_ALERT) {
            $this->createAlert($idProperty, 'HK_OVERLOAD', 'warning',
                $ops['housekeeping']['pending'] . ' habitaciones pendientes de limpieza',
                'Housekeeping tiene ' . $ops['housekeeping']['pending'] . ' tareas pendientes. Considerar refuerzo de equipo.',
                ['pending' => $ops['housekeeping']['pending']]
            );
        }
    }

    /**
     * Crear una alerta operacional.
     */
    public function createAlert(
        int $idProperty, string $type, string $severity,
        string $title, string $message = '', array $data = []
    ): int {
        // Evitar duplicados de la misma alerta en las últimas 2 horas
        $exists = Db::getInstance()->getValue(
            'SELECT id FROM `' . _DB_PREFIX_ . 'smart_dashboard_alert`
             WHERE id_property = ' . (int) $idProperty . '
             AND alert_type = "' . pSQL($type) . '"
             AND resolved = 0
             AND created_at > DATE_SUB(NOW(), INTERVAL 2 HOUR)'
        );
        if ($exists) {
            return (int) $exists;
        }

        Db::getInstance()->insert('smart_dashboard_alert', [
            'id_property' => (int) $idProperty,
            'alert_type'  => pSQL($type),
            'severity'    => pSQL($severity),
            'title'       => pSQL($title),
            'message'     => pSQL($message),
            'data'        => pSQL(json_encode($data)),
        ]);

        $alertId = (int) Db::getInstance()->Insert_ID();

        // Notificar por email/WhatsApp si está configurado
        if ($severity === 'critical') {
            $this->notifyAlert($alertId, $title, $message);
        }

        return $alertId;
    }

    public function resolveAlert(int $idAlert, int $idAgent): bool
    {
        return Db::getInstance()->update('smart_dashboard_alert',
            ['resolved' => 1, 'read_by' => (int) $idAgent, 'read_at' => date('Y-m-d H:i:s')],
            'id = ' . (int) $idAlert
        );
    }

    public function getActiveAlerts(int $idProperty): array
    {
        return Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'smart_dashboard_alert`
             WHERE id_property = ' . (int) $idProperty . '
             AND resolved = 0
             ORDER BY FIELD(severity,"critical","warning","info"), created_at DESC
             LIMIT 20'
        ) ?: [];
    }

    // ──────────────────────────────────────────────────────────
    //  SNAPSHOT DIARIO (ejecutar via cron al fin del día)
    // ──────────────────────────────────────────────────────────

    /**
     * Guardar snapshot de KPIs del día.
     * Llamar desde cron a las 23:59 cada noche.
     */
    public function saveSnapshot(int $idProperty = 1, string $date = ''): bool
    {
        if (!$date) {
            $date = date('Y-m-d');
        }

        $occ = $this->getOccupancyData($idProperty, $date);
        $rev = $this->getRevenueData($idProperty, $date);

        $data = [
            'id_property'    => (int) $idProperty,
            'snapshot_date'  => pSQL($date),
            'total_rooms'    => $occ['total_rooms'],
            'occupied_rooms' => $occ['occupied'],
            'arrivals'       => $occ['arrivals_total'],
            'departures'     => $occ['departures'],
            'in_house'       => $occ['in_house'],
            'no_shows'       => $occ['no_shows'],
            'adr'            => $rev['adr'],
            'revpar'         => $rev['revpar'],
            'total_revenue'  => $rev['total'],
            'rooms_revenue'  => $rev['rooms'],
            'fnb_revenue'    => $rev['fnb'],
            'spa_revenue'    => $rev['spa'],
        ];

        // Upsert
        $exists = Db::getInstance()->getValue(
            'SELECT id FROM `' . _DB_PREFIX_ . 'smart_dashboard_snapshot`
             WHERE id_property = ' . $idProperty . ' AND snapshot_date = "' . pSQL($date) . '"'
        );

        if ($exists) {
            return Db::getInstance()->update('smart_dashboard_snapshot', $data,
                'id_property = ' . $idProperty . ' AND snapshot_date = "' . pSQL($date) . '"'
            );
        }

        return (bool) Db::getInstance()->insert('smart_dashboard_snapshot', $data);
    }

    // ──────────────────────────────────────────────────────────
    //  HELPERS PRIVADOS
    // ──────────────────────────────────────────────────────────

    private function getOccupancyStatus(float $pct): string
    {
        if ($pct >= 90) return 'critical_high';
        if ($pct >= 70) return 'good';
        if ($pct >= 50) return 'moderate';
        if ($pct >= 30) return 'low';
        return 'critical_low';
    }

    private function getTargets(int $idProperty): array
    {
        $row = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'smart_dashboard_targets`
             WHERE id_property = ' . (int) $idProperty
        );
        return $row ?: [
            'target_occupancy' => 75.0,
            'target_adr'       => 0,
            'target_revpar'    => 0,
            'target_nps'       => 72.0,
        ];
    }

    private function getSnapshotData(int $idProperty, string $date): ?array
    {
        $row = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'smart_dashboard_snapshot`
             WHERE id_property = ' . (int) $idProperty . '
             AND snapshot_date = "' . pSQL($date) . '"'
        );
        return $row ?: null;
    }

    private function countAlertsBySeverity(int $idProperty, string $severity): int
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'smart_dashboard_alert`
             WHERE id_property = ' . (int) $idProperty . '
             AND severity = "' . pSQL($severity) . '" AND resolved = 0'
        );
    }

    private function formatChartData(array $snapshots): array
    {
        $labels  = [];
        $revpar  = [];
        $occ     = [];
        $revenue = [];

        foreach ($snapshots as $s) {
            $labels[]  = date('d/m', strtotime($s['snapshot_date']));
            $revpar[]  = (float) $s['revpar'];
            $revenue[] = (float) $s['total_revenue'];
            $occ[]     = $s['total_rooms'] > 0
                ? round(($s['occupied_rooms'] / $s['total_rooms']) * 100, 1)
                : 0;
        }

        return compact('labels', 'revpar', 'occ', 'revenue');
    }

    private function notifyAlert(int $alertId, string $title, string $message): void
    {
        // Notificar por email al director
        $email = Configuration::get('SMART_DASH_ALERT_EMAIL');
        if ($email) {
            Mail::send(
                (int) Configuration::get('PS_LANG_DEFAULT'),
                'smart_alert',
                '[SmartHotelOS] Alerta crítica: ' . $title,
                ['title' => $title, 'message' => $message, 'url' => Context::getContext()->link->getAdminLink('AdminSmartDashboardAlerts')],
                $email
            );
        }
    }

    public function getContent(): string
    {
        return $this->display(__FILE__, 'views/templates/admin/configuration.tpl');
    }
}
