<?php
/**
 * SmartHotelOS — Módulo Smart Spa & Wellness
 * Grupo Smart de Administración
 *
 * Reservas de spa y tratamientos, gestión de terapeutas,
 * control de agenda, paquetes wellness y acceso a gimnasio.
 *
 * @module  smart_spa
 * @version 1.0.0
 */

if (!defined('_PS_VERSION_')) { exit; }

class Smart_Spa extends Module
{
    const MODULE_VERSION = '1.0.0';

    const SERVICE_TYPES = [
        'MASSAGE'     => 'Masaje',
        'FACIAL'      => 'Tratamiento facial',
        'BODY'        => 'Tratamiento corporal',
        'HYDROTHERAPY'=> 'Hidroterapia',
        'MANICURE'    => 'Manicure / Pedicure',
        'HAIR'        => 'Cabello',
        'FITNESS'     => 'Entrenamiento personal',
        'YOGA'        => 'Yoga / Meditación',
        'PACKAGE'     => 'Paquete wellness',
    ];

    const BOOKING_STATUS = [
        'CONFIRMED' => 'Confirmada',
        'CHECKED_IN'=> 'En tratamiento',
        'COMPLETED' => 'Completada',
        'CANCELLED' => 'Cancelada',
        'NO_SHOW'   => 'No se presentó',
    ];

    public function __construct()
    {
        $this->name      = 'smart_spa';
        $this->tab       = 'administration';
        $this->version   = self::MODULE_VERSION;
        $this->author    = 'Smart Hotel Group';
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('Smart Spa & Wellness');
        $this->description = $this->l(
            'Reservas de spa, gestión de terapeutas, agenda de tratamientos, ' .
            'paquetes wellness y control de acceso al gimnasio.'
        );
    }

    public function install(): bool
    {
        return parent::install()
            && $this->installSql()
            && $this->installTabs()
            && $this->registerHook(['displaySmartDashboardWidgets', 'actionSmartCheckIn'])
            && $this->installDefaultConfig();
    }

    public function uninstall(): bool
    {
        return parent::uninstall() && $this->uninstallSql() && $this->uninstallTabs();
    }

    private function installSql(): bool
    {
        $queries = [

            // Instalaciones del spa (salas de tratamiento, piscina, sauna, etc.)
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_spa_facility` (
                `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `id_property`   INT UNSIGNED NOT NULL DEFAULT 1,
                `name`          VARCHAR(100) NOT NULL,
                `facility_type` VARCHAR(30) DEFAULT "TREATMENT_ROOM",
                `capacity`      TINYINT UNSIGNED DEFAULT 1,
                `description`   TEXT,
                `active`        TINYINT(1) DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            // Servicios / tratamientos ofrecidos
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_spa_service` (
                `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `id_property`     INT UNSIGNED NOT NULL DEFAULT 1,
                `name`            VARCHAR(200) NOT NULL,
                `name_en`         VARCHAR(200),
                `service_type`    VARCHAR(30) NOT NULL DEFAULT "MASSAGE",
                `description`     TEXT,
                `duration_min`    SMALLINT UNSIGNED NOT NULL DEFAULT 60,
                `price`           DECIMAL(10,2) NOT NULL,
                `price_couple`    DECIMAL(10,2) NULL,
                `is_couples`      TINYINT(1) DEFAULT 0,
                `requires_gender` ENUM("","M","F") DEFAULT "",
                `min_age`         TINYINT UNSIGNED DEFAULT 18,
                `prep_time_min`   TINYINT UNSIGNED DEFAULT 15 COMMENT "Tiempo de preparación de la sala",
                `image_url`       VARCHAR(500),
                `active`          TINYINT(1) DEFAULT 1,
                `sort_order`      TINYINT UNSIGNED DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            // Terapeutas
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_spa_therapist` (
                `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `id_property`   INT UNSIGNED NOT NULL DEFAULT 1,
                `firstname`     VARCHAR(100) NOT NULL,
                `lastname`      VARCHAR(100) NOT NULL,
                `gender`        ENUM("M","F") DEFAULT "F",
                `specialties`   JSON COMMENT "Array de service_types que domina",
                `languages`     JSON COMMENT "Idiomas que habla",
                `rating`        DECIMAL(3,2) DEFAULT 0.00,
                `total_sessions`SMALLINT UNSIGNED DEFAULT 0,
                `photo_url`     VARCHAR(500),
                `bio`           TEXT,
                `active`        TINYINT(1) DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            // Horarios de terapeutas (bloques de disponibilidad)
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

            // Reservas de spa
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_spa_booking` (
                `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `id_property`     INT UNSIGNED NOT NULL DEFAULT 1,
                `id_service`      INT UNSIGNED NOT NULL,
                `id_therapist`    INT UNSIGNED NULL,
                `id_facility`     INT UNSIGNED NULL,
                `id_customer`     INT UNSIGNED NOT NULL,
                `id_booking`      INT UNSIGNED NULL COMMENT "Reserva de hotel asociada",
                `booking_ref`     VARCHAR(20) NOT NULL UNIQUE,
                `status`          VARCHAR(20) DEFAULT "CONFIRMED",
                `appointment_date` DATE NOT NULL,
                `start_time`      TIME NOT NULL,
                `end_time`        TIME NOT NULL,
                `guests`          TINYINT UNSIGNED DEFAULT 1,
                `price`           DECIMAL(10,2) NOT NULL,
                `notes`           TEXT,
                `health_notes`    TEXT COMMENT "Condiciones médicas relevantes",
                `confirmed_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
                `checkin_at`      DATETIME NULL,
                `completed_at`    DATETIME NULL,
                `cancelled_at`    DATETIME NULL,
                `rating`          TINYINT UNSIGNED NULL,
                `feedback`        TEXT,
                INDEX `idx_date`       (`appointment_date`, `status`),
                INDEX `idx_therapist`  (`id_therapist`, `appointment_date`),
                INDEX `idx_customer`   (`id_customer`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            // Accesos al gimnasio / wellness areas
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_spa_gym_access` (
                `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `id_customer`   INT UNSIGNED NOT NULL,
                `id_booking`    INT UNSIGNED NULL,
                `area`          VARCHAR(50) DEFAULT "GYM",
                `entered_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
                `exited_at`     DATETIME NULL,
                INDEX `idx_customer_date` (`id_customer`, `entered_at`)
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
            ['AdminSmartSpa',          'Spa & Wellness',        'AdminSmartHotel'],
            ['AdminSmartSpaAgenda',    'Agenda del Spa',         'AdminSmartSpa'],
            ['AdminSmartSpaBookings',  'Reservas',               'AdminSmartSpa'],
            ['AdminSmartSpaTherapists','Terapeutas',             'AdminSmartSpa'],
            ['AdminSmartSpaServices',  'Servicios / Menú Spa',   'AdminSmartSpa'],
            ['AdminSmartSpaReports',   'Reportes Wellness',      'AdminSmartSpa'],
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
            'SMART_SPA_ADVANCE_BOOKING_HOURS' => 2,
            'SMART_SPA_CANCEL_HOURS'          => 24,
            'SMART_SPA_ONLINE_BOOKING'        => 1,
            'SMART_SPA_SEND_REMINDERS'        => 1,
            'SMART_SPA_REMINDER_HOURS'        => 2,
            'SMART_SPA_GYM_HOURS'             => '06:00-22:00',
        ] as $k => $v) {
            Configuration::updateValue($k, $v);
        }
        return true;
    }

    private function uninstallSql(): bool
    {
        foreach (['smart_spa_facility','smart_spa_service','smart_spa_therapist',
                  'smart_spa_schedule','smart_spa_booking','smart_spa_gym_access'] as $t) {
            Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . pSQL($t) . '`');
        }
        return true;
    }

    private function uninstallTabs(): bool
    {
        foreach (['AdminSmartSpa','AdminSmartSpaAgenda','AdminSmartSpaBookings',
                  'AdminSmartSpaTherapists','AdminSmartSpaServices','AdminSmartSpaReports'] as $c) {
            $id = (int) Tab::getIdFromClassName($c);
            if ($id) { (new Tab($id))->delete(); }
        }
        return true;
    }

    // ──────────────────────────────────────────────────────────
    //  LÓGICA DE NEGOCIO
    // ──────────────────────────────────────────────────────────

    /**
     * Obtener disponibilidad del spa para una fecha y servicio.
     */
    public function getAvailability(int $idService, string $date, ?int $idTherapist = null): array
    {
        $service = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'smart_spa_service` WHERE id = ' . (int) $idService
        );
        if (!$service) { return []; }

        $durationMin = (int) $service['duration_min'];
        $prepMin     = (int) $service['prep_time_min'];
        $totalMin    = $durationMin + $prepMin;

        // Horario del spa (09:00 - 20:00 por defecto)
        $openHour  = 9;
        $closeHour = 20;

        // Obtener slots ocupados
        $bookedSlots = Db::getInstance()->executeS(
            'SELECT start_time, end_time, id_therapist
             FROM `' . _DB_PREFIX_ . 'smart_spa_booking`
             WHERE appointment_date = "' . pSQL($date) . '"
             AND status NOT IN ("CANCELLED","NO_SHOW")'
            . ($idTherapist ? ' AND id_therapist = ' . (int) $idTherapist : '')
        ) ?: [];

        $slots     = [];
        $startMin  = $openHour * 60;
        $endMin    = $closeHour * 60 - $totalMin;

        for ($m = $startMin; $m <= $endMin; $m += 30) {
            $slotStart = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
            $slotEnd   = sprintf('%02d:%02d', intdiv($m + $durationMin, 60), ($m + $durationMin) % 60);
            $available = true;

            foreach ($bookedSlots as $booked) {
                // Verificar solapamiento
                if ($slotStart < $booked['end_time'] && $slotEnd > $booked['start_time']) {
                    $available = false;
                    break;
                }
            }

            if ($available) {
                $slots[] = [
                    'start'     => $slotStart,
                    'end'       => $slotEnd,
                    'available' => true,
                ];
            }
        }

        return [
            'service'  => $service,
            'date'     => $date,
            'slots'    => $slots,
            'total'    => count($slots),
        ];
    }

    /**
     * Crear una reserva de spa.
     */
    public function createBooking(array $data): array
    {
        // Validar disponibilidad
        $availability = $this->getAvailability(
            (int) $data['id_service'],
            $data['date'],
            isset($data['id_therapist']) ? (int) $data['id_therapist'] : null
        );

        $slotAvailable = false;
        foreach ($availability['slots'] as $slot) {
            if ($slot['start'] === $data['time']) {
                $slotAvailable = true;
                break;
            }
        }

        if (!$slotAvailable) {
            return ['success' => false, 'message' => 'El horario seleccionado no está disponible'];
        }

        $service   = $availability['service'];
        $startTime = $data['time'];
        $endTime   = $this->addMinutes($startTime, (int) $service['duration_min']);
        $ref       = 'SPA-' . strtoupper(substr(uniqid(), -6));

        Db::getInstance()->insert('smart_spa_booking', [
            'id_property'      => (int) ($data['id_property'] ?? 1),
            'id_service'       => (int) $data['id_service'],
            'id_therapist'     => isset($data['id_therapist']) ? (int) $data['id_therapist'] : null,
            'id_customer'      => (int) $data['id_customer'],
            'id_booking'       => isset($data['id_booking']) ? (int) $data['id_booking'] : null,
            'booking_ref'      => pSQL($ref),
            'status'           => 'CONFIRMED',
            'appointment_date' => pSQL($data['date']),
            'start_time'       => pSQL($startTime),
            'end_time'         => pSQL($endTime),
            'guests'           => (int) ($data['guests'] ?? 1),
            'price'            => (float) ($data['guests'] == 2 && $service['price_couple']
                                    ? $service['price_couple'] : $service['price']),
            'notes'            => pSQL($data['notes'] ?? ''),
            'health_notes'     => pSQL($data['health_notes'] ?? ''),
        ]);

        $idSpaBooking = (int) Db::getInstance()->Insert_ID();

        // Enviar confirmación
        if (Configuration::get('SMART_SPA_SEND_REMINDERS')) {
            Hook::exec('actionSmartSendNotification', [
                'type'        => 'spa_booking_confirmed',
                'id_customer' => $data['id_customer'],
                'data'        => ['ref' => $ref, 'date' => $data['date'], 'time' => $startTime],
            ]);
        }

        return [
            'success'     => true,
            'id_booking'  => $idSpaBooking,
            'booking_ref' => $ref,
            'start_time'  => $startTime,
            'end_time'    => $endTime,
        ];
    }

    /**
     * Agenda del día para supervisora de spa.
     */
    public function getDailyAgenda(int $idProperty, string $date): array
    {
        $bookings = Db::getInstance()->executeS(
            'SELECT sb.*, ss.name as service_name, ss.duration_min,
                    CONCAT(st.firstname, " ", st.lastname) as therapist_name,
                    c.firstname as guest_firstname, c.lastname as guest_lastname
             FROM `' . _DB_PREFIX_ . 'smart_spa_booking` sb
             JOIN `' . _DB_PREFIX_ . 'smart_spa_service` ss ON ss.id = sb.id_service
             LEFT JOIN `' . _DB_PREFIX_ . 'smart_spa_therapist` st ON st.id = sb.id_therapist
             JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer = sb.id_customer
             WHERE sb.id_property = ' . (int) $idProperty . '
             AND sb.appointment_date = "' . pSQL($date) . '"
             AND sb.status NOT IN ("CANCELLED","NO_SHOW")
             ORDER BY sb.start_time ASC, st.firstname ASC'
        ) ?: [];

        // Agrupar por terapeuta
        $byTherapist = [];
        foreach ($bookings as $b) {
            $key = $b['therapist_name'] ?: 'Sin asignar';
            $byTherapist[$key][] = $b;
        }

        $totalRevenue = array_sum(array_column($bookings, 'price'));

        return [
            'date'           => $date,
            'total_bookings' => count($bookings),
            'total_revenue'  => round($totalRevenue, 2),
            'bookings'       => $bookings,
            'by_therapist'   => $byTherapist,
        ];
    }

    private function addMinutes(string $time, int $minutes): string
    {
        $ts = strtotime("1970-01-01 {$time}:00") + $minutes * 60;
        return date('H:i', $ts);
    }

    private function uninstallTabs2(): bool { return true; }
    public function getContent(): string { return $this->display(__FILE__, 'views/templates/admin/configuration.tpl'); }
}
