<?php
/**
 * SmartHotelOS — Módulo Smart Guest Profile (CRM)
 * Grupo Smart de Administración
 *
 * Perfil 360° del huésped: historial, preferencias, alergias,
 * celebraciones, segmentación VIP y personalización de la experiencia.
 * Estándar Forbes 5 estrellas: anticipar, recordar, sorprender.
 *
 * @module      smart_guestprofile
 * @version     1.0.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Smart_Guestprofile extends Module
{
    const MODULE_VERSION = '1.0.0';

    /** Segmentos de huéspedes */
    const SEGMENTS = [
        'STANDARD'   => 'Estándar',
        'FREQUENT'   => 'Frecuente (3+ estancias)',
        'VIP'        => 'VIP',
        'VIP_PLUS'   => 'VIP Plus (Gold Member)',
        'CORPORATE'  => 'Corporativo',
        'CELEBRANT'  => 'Celebración especial',
        'HONEYMOON'  => 'Luna de miel',
        'FAMILY'     => 'Familia con niños',
        'ACCESSIBLE' => 'Necesidades especiales',
    ];

    /** Categorías de preferencias */
    const PREF_CATEGORIES = [
        'ROOM'          => 'Habitación',
        'PILLOW'        => 'Almohadas',
        'TEMPERATURE'   => 'Temperatura',
        'FLOOR'         => 'Piso preferido',
        'NEWSPAPER'     => 'Diario/Revista',
        'MINIBAR'       => 'Minibar',
        'DIETARY'       => 'Restricciones alimentarias',
        'ALLERGY'       => 'Alergias',
        'ARRIVAL'       => 'Llegada',
        'TURNDOWN'      => 'Servicio nocturno',
        'COMMUNICATION' => 'Comunicación preferida',
        'TRANSPORT'     => 'Transporte',
        'SPECIAL'       => 'Solicitud especial recurrente',
    ];

    /** Tipos de ocasión especial */
    const OCCASIONS = [
        'BIRTHDAY'    => 'Cumpleaños',
        'ANNIVERSARY' => 'Aniversario',
        'HONEYMOON'   => 'Luna de miel',
        'GRADUATION'  => 'Graduación',
        'BUSINESS'    => 'Viaje de negocios',
        'VACATION'    => 'Vacaciones',
        'WELLNESS'    => 'Bienestar / Retiro',
    ];

    public function __construct()
    {
        $this->name      = 'smart_guestprofile';
        $this->tab       = 'administration';
        $this->version   = self::MODULE_VERSION;
        $this->author    = 'Smart Hotel Group';
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('Smart Guest Profile CRM');
        $this->description = $this->l(
            'CRM hotelero 360°: historial de estancias, preferencias personales, ' .
            'alergias, celebraciones y segmentación VIP. Forbes 5 estrellas.'
        );
    }

    public function install(): bool
    {
        return parent::install()
            && $this->installSql()
            && $this->installTabs()
            && $this->registerHook([
                'actionSmartCheckIn',
                'actionSmartCheckOut',
                'actionSmartBookingCreated',
                'displaySmartFrontDeskGuestInfo',
                'displaySmartDashboardWidgets',
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

            // Perfil extendido del huésped (complementa ps_customer)
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_guest_profile` (
                `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `id_customer`       INT UNSIGNED NOT NULL UNIQUE,
                `guest_code`        VARCHAR(20) UNIQUE COMMENT "Código de huésped, ej: SG-000123",
                `nationality`       CHAR(2),
                `passport_number`   VARCHAR(50),
                `date_of_birth`     DATE NULL,
                `gender`            ENUM("M","F","X","") DEFAULT "",
                `language_code`     VARCHAR(5) DEFAULT "es",
                `segment`           VARCHAR(20) DEFAULT "STANDARD",
                `vip_level`         TINYINT UNSIGNED DEFAULT 0 COMMENT "0=no VIP, 1-5 niveles",
                `loyalty_points`    INT UNSIGNED DEFAULT 0,
                `total_stays`       SMALLINT UNSIGNED DEFAULT 0,
                `total_nights`      SMALLINT UNSIGNED DEFAULT 0,
                `total_spent`       DECIMAL(14,2) DEFAULT 0.00,
                `first_stay_date`   DATE NULL,
                `last_stay_date`    DATE NULL,
                `last_stay_property` INT UNSIGNED NULL,
                `avg_daily_spend`   DECIMAL(10,2) DEFAULT 0.00,
                `nps_score`         TINYINT NULL COMMENT "Última puntuación NPS dada",
                `is_blocked`        TINYINT(1) DEFAULT 0,
                `block_reason`      TEXT NULL,
                `internal_notes`    TEXT NULL COMMENT "Notas privadas del staff",
                `concierge_notes`   TEXT NULL,
                `created_at`        DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at`        DATETIME ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_segment`    (`segment`),
                INDEX `idx_vip`        (`vip_level`),
                INDEX `idx_last_stay`  (`last_stay_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            // Preferencias individuales del huésped
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_guest_preference` (
                `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `id_guest`        INT UNSIGNED NOT NULL REFERENCES smart_guest_profile(id),
                `category`        VARCHAR(30) NOT NULL,
                `preference_key`  VARCHAR(100) NOT NULL,
                `preference_val`  TEXT,
                `source`          ENUM("staff","guest","booking","system") DEFAULT "staff",
                `confirmed`       TINYINT(1) DEFAULT 1,
                `id_property`     INT UNSIGNED NULL COMMENT "NULL = aplica a todos los hoteles",
                `created_by`      INT UNSIGNED NULL,
                `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at`      DATETIME ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_guest_cat` (`id_guest`, `category`),
                UNIQUE KEY `uk_guest_key_prop` (`id_guest`, `preference_key`, `id_property`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            // Alergias e intolerancias (tabla separada por importancia)
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_guest_allergy` (
                `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `id_guest`        INT UNSIGNED NOT NULL REFERENCES smart_guest_profile(id),
                `allergy_type`    VARCHAR(50) NOT NULL COMMENT "gluten, lactosa, mariscos, etc.",
                `severity`        ENUM("intolerance","allergy","anaphylaxis") DEFAULT "allergy",
                `notes`           VARCHAR(300),
                `verified`        TINYINT(1) DEFAULT 0,
                `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_guest` (`id_guest`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            // Ocasiones especiales / celebraciones
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_guest_occasion` (
                `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `id_guest`        INT UNSIGNED NOT NULL REFERENCES smart_guest_profile(id),
                `occasion_type`   VARCHAR(30) NOT NULL,
                `occasion_date`   DATE NOT NULL COMMENT "Fecha de la celebración (año ignorado para aniversarios)",
                `partner_name`    VARCHAR(100) NULL,
                `notes`           TEXT,
                `remind_days`     TINYINT UNSIGNED DEFAULT 7 COMMENT "Cuántos días antes alertar",
                `active`          TINYINT(1) DEFAULT 1,
                INDEX `idx_guest` (`id_guest`),
                INDEX `idx_date`  (`occasion_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            // Historial de estancias vinculado al perfil
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_guest_stay_history` (
                `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `id_guest`        INT UNSIGNED NOT NULL REFERENCES smart_guest_profile(id),
                `id_booking`      INT UNSIGNED NOT NULL,
                `id_property`     INT UNSIGNED NOT NULL DEFAULT 1,
                `id_room_type`    INT UNSIGNED,
                `id_room`         INT UNSIGNED,
                `checkin_date`    DATE NOT NULL,
                `checkout_date`   DATE NOT NULL,
                `nights`          TINYINT UNSIGNED,
                `adults`          TINYINT UNSIGNED DEFAULT 1,
                `children`        TINYINT UNSIGNED DEFAULT 0,
                `total_spent`     DECIMAL(12,2) DEFAULT 0.00,
                `room_rate`       DECIMAL(10,2) DEFAULT 0.00,
                `rate_plan`       VARCHAR(50),
                `channel`         VARCHAR(30) COMMENT "booking_com, direct, corporate, etc.",
                `nps_score`       TINYINT NULL,
                `nps_comment`     TEXT NULL,
                `was_vip`         TINYINT(1) DEFAULT 0,
                `special_requests' TEXT,
                `staff_notes`     TEXT,
                `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_guest`    (`id_guest`),
                INDEX `idx_booking`  (`id_booking`),
                INDEX `idx_checkin`  (`checkin_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            // Tags / etiquetas del huésped
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_guest_tag` (
                `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `id_guest`     INT UNSIGNED NOT NULL REFERENCES smart_guest_profile(id),
                `tag`          VARCHAR(50) NOT NULL,
                `color`        VARCHAR(7) DEFAULT "#C9A84C",
                `created_by`   INT UNSIGNED,
                `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uk_guest_tag` (`id_guest`, `tag`),
                INDEX `idx_tag` (`tag`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        ];

        foreach ($queries as $sql) {
            if (!Db::getInstance()->execute($sql)) {
                return false;
            }
        }
        return true;
    }

    private function installTabs(): bool
    {
        foreach ([
            ['AdminSmartGuestProfile',   'Perfil de Huéspedes', 'AdminSmartHotel'],
            ['AdminSmartGuestList',       'Todos los Huéspedes', 'AdminSmartGuestProfile'],
            ['AdminSmartGuestVip',        'Huéspedes VIP',       'AdminSmartGuestProfile'],
            ['AdminSmartGuestOccasions',  'Celebraciones',       'AdminSmartGuestProfile'],
        ] as [$cls, $name, $parent]) {
            $tab = new Tab();
            $tab->active = 1; $tab->class_name = $cls; $tab->module = $this->name;
            $tab->id_parent = (int) Tab::getIdFromClassName($parent);
            foreach (Language::getLanguages() as $l) { $tab->name[$l['id_lang']] = $name; }
            $tab->add();
        }
        return true;
    }

    private function installDefaultConfig(): bool
    {
        foreach ([
            'SMART_GP_VIP_STAYS_THRESHOLD'   => 5,      // estancias para VIP automático
            'SMART_GP_VIP_SPEND_THRESHOLD'   => 500000, // gasto en CLP para VIP
            'SMART_GP_OCCASION_REMIND_DAYS'  => 7,
            'SMART_GP_AUTO_SEGMENT'          => 1,
            'SMART_GP_SHOW_PHOTO'            => 1,
            'SMART_GP_LOYALTY_ENABLED'       => 0,      // futuro: programa de puntos
        ] as $k => $v) {
            Configuration::updateValue($k, $v);
        }
        return true;
    }

    private function uninstallSql(): bool
    {
        foreach (['smart_guest_profile','smart_guest_preference','smart_guest_allergy',
                  'smart_guest_occasion','smart_guest_stay_history','smart_guest_tag'] as $t) {
            Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . pSQL($t) . '`');
        }
        return true;
    }

    private function uninstallTabs(): bool
    {
        foreach (['AdminSmartGuestProfile','AdminSmartGuestList','AdminSmartGuestVip','AdminSmartGuestOccasions'] as $c) {
            $id = (int) Tab::getIdFromClassName($c);
            if ($id) { (new Tab($id))->delete(); }
        }
        return true;
    }

    // ──────────────────────────────────────────────────────────
    //  HOOKS
    // ──────────────────────────────────────────────────────────

    /** Al hacer check-in: mostrar info del perfil en Front Desk */
    public function hookDisplaySmartFrontDeskGuestInfo(array $params): string
    {
        $idCustomer = (int) ($params['id_customer'] ?? 0);
        if (!$idCustomer) {
            return '';
        }

        $profile     = $this->getOrCreateProfile($idCustomer);
        $preferences = $this->getPreferences($profile['id']);
        $allergies   = $this->getAllergies($profile['id']);
        $occasions   = $this->getUpcomingOccasions($profile['id'], 30);
        $tags        = $this->getTags($profile['id']);

        $this->context->smarty->assign(compact('profile', 'preferences', 'allergies', 'occasions', 'tags'));
        return $this->display(__FILE__, 'views/templates/hook/frontdesk_guest_info.tpl');
    }

    /** Al hacer check-out: actualizar historial y recalcular segmento */
    public function hookActionSmartCheckOut(array $params): void
    {
        $idBooking  = (int) ($params['id_booking'] ?? 0);
        $idCustomer = $this->getCustomerFromBooking($idBooking);

        if (!$idCustomer) {
            return;
        }

        $profile = $this->getOrCreateProfile($idCustomer);
        $this->recordStayHistory($profile['id'], $idBooking);
        $this->recalculateSegment($profile['id']);
        $this->updateStayStats($profile['id']);
    }

    // ──────────────────────────────────────────────────────────
    //  LÓGICA DE NEGOCIO
    // ──────────────────────────────────────────────────────────

    /**
     * Obtener o crear un perfil de huésped.
     */
    public function getOrCreateProfile(int $idCustomer): array
    {
        $profile = Db::getInstance()->getRow(
            'SELECT gp.*, c.firstname, c.lastname, c.email, c.phone
             FROM `' . _DB_PREFIX_ . 'smart_guest_profile` gp
             JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer = gp.id_customer
             WHERE gp.id_customer = ' . (int) $idCustomer
        );

        if (!$profile) {
            // Crear perfil nuevo
            $customer = new Customer($idCustomer);
            if (!Validate::isLoadedObject($customer)) {
                return [];
            }

            $guestCode = $this->generateGuestCode();
            Db::getInstance()->insert('smart_guest_profile', [
                'id_customer' => $idCustomer,
                'guest_code'  => pSQL($guestCode),
                'segment'     => 'STANDARD',
            ]);

            $profile = $this->getOrCreateProfile($idCustomer);
        }

        return $profile ?: [];
    }

    /**
     * Actualizar preferencia del huésped.
     */
    public function setPreference(
        int $idGuest, string $category, string $key, string $value,
        string $source = 'staff', ?int $idProperty = null
    ): bool {
        $existing = Db::getInstance()->getValue(
            'SELECT id FROM `' . _DB_PREFIX_ . 'smart_guest_preference`
             WHERE id_guest = ' . (int) $idGuest . '
             AND preference_key = "' . pSQL($key) . '"
             AND ' . ($idProperty ? 'id_property = ' . (int) $idProperty : 'id_property IS NULL')
        );

        $data = [
            'category'        => pSQL($category),
            'preference_key'  => pSQL($key),
            'preference_val'  => pSQL($value),
            'source'          => pSQL($source),
            'id_property'     => $idProperty,
            'updated_at'      => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            return Db::getInstance()->update('smart_guest_preference', $data, 'id = ' . (int) $existing);
        }

        $data['id_guest']    = (int) $idGuest;
        $data['created_at']  = date('Y-m-d H:i:s');
        return (bool) Db::getInstance()->insert('smart_guest_preference', $data);
    }

    /**
     * Añadir alergia / intolerancia al perfil.
     */
    public function addAllergy(int $idGuest, string $allergyType, string $severity = 'allergy', string $notes = ''): bool
    {
        // Verificar que no exista
        $exists = Db::getInstance()->getValue(
            'SELECT id FROM `' . _DB_PREFIX_ . 'smart_guest_allergy`
             WHERE id_guest = ' . (int) $idGuest . ' AND allergy_type = "' . pSQL($allergyType) . '"'
        );
        if ($exists) {
            return true;
        }

        return (bool) Db::getInstance()->insert('smart_guest_allergy', [
            'id_guest'     => (int) $idGuest,
            'allergy_type' => pSQL($allergyType),
            'severity'     => pSQL($severity),
            'notes'        => pSQL($notes),
        ]);
    }

    /**
     * Añadir ocasión especial al perfil.
     */
    public function addOccasion(int $idGuest, array $data): int
    {
        Db::getInstance()->insert('smart_guest_occasion', [
            'id_guest'      => (int) $idGuest,
            'occasion_type' => pSQL($data['type'] ?? 'BIRTHDAY'),
            'occasion_date' => pSQL($data['date'] ?? date('Y-m-d')),
            'partner_name'  => pSQL($data['partner_name'] ?? ''),
            'notes'         => pSQL($data['notes'] ?? ''),
            'remind_days'   => (int) ($data['remind_days'] ?? 7),
        ]);
        return (int) Db::getInstance()->Insert_ID();
    }

    /**
     * Marcar huésped como VIP con nivel.
     */
    public function setVip(int $idGuest, int $level, string $notes = ''): bool
    {
        $updated = Db::getInstance()->update('smart_guest_profile',
            ['vip_level' => (int) $level, 'segment' => $level > 0 ? 'VIP' : 'STANDARD'],
            'id = ' . (int) $idGuest
        );

        if ($updated && $notes) {
            $this->addTag($idGuest, 'VIP-' . $level);
            $this->appendNote($idGuest, 'VIP asignado (nivel ' . $level . '): ' . $notes);
        }

        return $updated;
    }

    /**
     * Re-calcular segmento del huésped automáticamente.
     */
    public function recalculateSegment(int $idGuest): void
    {
        $profile = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'smart_guest_profile` WHERE id = ' . (int) $idGuest
        );
        if (!$profile) {
            return;
        }

        // Si ya es VIP manual, no tocar
        if ((int) $profile['vip_level'] > 0) {
            return;
        }

        $vipStays = (int) Configuration::get('SMART_GP_VIP_STAYS_THRESHOLD');
        $vipSpend = (float) Configuration::get('SMART_GP_VIP_SPEND_THRESHOLD');

        $segment = 'STANDARD';
        if ((int) $profile['total_stays'] >= $vipStays || (float) $profile['total_spent'] >= $vipSpend) {
            $segment = 'FREQUENT';
        }
        if ((int) $profile['total_stays'] >= ($vipStays * 3) || (float) $profile['total_spent'] >= ($vipSpend * 5)) {
            $segment = 'VIP';
        }

        Db::getInstance()->update('smart_guest_profile',
            ['segment' => pSQL($segment)],
            'id = ' . (int) $idGuest
        );
    }

    /**
     * Generar ficha completa del huésped para recepción (briefing de llegada).
     */
    public function getArrivalBriefing(int $idCustomer): array
    {
        $profile     = $this->getOrCreateProfile($idCustomer);
        if (empty($profile)) {
            return [];
        }

        $guestId     = (int) $profile['id'];
        $preferences = $this->getPreferences($guestId);
        $allergies   = $this->getAllergies($guestId);
        $occasions   = $this->getUpcomingOccasions($guestId, 5);
        $lastStay    = $this->getLastStay($guestId);
        $tags        = $this->getTags($guestId);

        // Construir mensajes de bienvenida personalizados para el staff
        $notes = [];

        if ($profile['vip_level'] > 0) {
            $notes[] = '★ HUÉSPED VIP NIVEL ' . $profile['vip_level'];
        }
        if ((int) $profile['total_stays'] > 1) {
            $notes[] = 'Regresa por ' . $profile['total_stays'] . ª vez. Última visita: ' . ($profile['last_stay_date'] ?: 'N/D');
        }
        if (!empty($allergies)) {
            $notes[] = '⚠️ ALERGIAS: ' . implode(', ', array_column($allergies, 'allergy_type'));
        }
        foreach ($occasions as $occ) {
            $notes[] = '🎉 CELEBRACIÓN: ' . ($occ['occasion_type']) . ' el ' . date('d/m', strtotime($occ['occasion_date']));
        }

        return [
            'profile'       => $profile,
            'preferences'   => $preferences,
            'allergies'     => $allergies,
            'occasions'     => $occasions,
            'last_stay'     => $lastStay,
            'tags'          => $tags,
            'staff_notes'   => $notes,
            'is_first_stay' => (int) $profile['total_stays'] === 0,
        ];
    }

    // ──────────────────────────────────────────────────────────
    //  CONSULTAS PRIVADAS
    // ──────────────────────────────────────────────────────────

    public function getPreferences(int $idGuest, ?string $category = null): array
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'smart_guest_preference`
                WHERE id_guest = ' . (int) $idGuest;
        if ($category) {
            $sql .= ' AND category = "' . pSQL($category) . '"';
        }
        $sql .= ' ORDER BY category, preference_key';
        return Db::getInstance()->executeS($sql) ?: [];
    }

    public function getAllergies(int $idGuest): array
    {
        return Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'smart_guest_allergy`
             WHERE id_guest = ' . (int) $idGuest . ' ORDER BY severity DESC'
        ) ?: [];
    }

    public function getUpcomingOccasions(int $idGuest, int $days = 30): array
    {
        return Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'smart_guest_occasion`
             WHERE id_guest = ' . (int) $idGuest . '
             AND active = 1
             AND DAYOFYEAR(occasion_date) BETWEEN DAYOFYEAR(CURDATE()) AND DAYOFYEAR(DATE_ADD(CURDATE(), INTERVAL ' . (int) $days . ' DAY))
             ORDER BY DAYOFYEAR(occasion_date) ASC
             LIMIT 5'
        ) ?: [];
    }

    public function getTags(int $idGuest): array
    {
        return Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'smart_guest_tag` WHERE id_guest = ' . (int) $idGuest
        ) ?: [];
    }

    private function addTag(int $idGuest, string $tag): void
    {
        Db::getInstance()->execute(
            'INSERT IGNORE INTO `' . _DB_PREFIX_ . 'smart_guest_tag`
             (id_guest, tag) VALUES (' . (int) $idGuest . ', "' . pSQL($tag) . '")'
        );
    }

    private function appendNote(int $idGuest, string $note): void
    {
        Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'smart_guest_profile`
             SET internal_notes = CONCAT(COALESCE(internal_notes,""), "\n[' . date('Y-m-d H:i') . '] ' . pSQL($note) . '")
             WHERE id = ' . (int) $idGuest
        );
    }

    private function generateGuestCode(): string
    {
        $last = Db::getInstance()->getValue(
            'SELECT MAX(CAST(SUBSTRING(guest_code, 4) AS UNSIGNED)) FROM `' . _DB_PREFIX_ . 'smart_guest_profile`'
        );
        return 'SG-' . str_pad(((int) $last) + 1, 6, '0', STR_PAD_LEFT);
    }

    private function getCustomerFromBooking(int $idBooking): int
    {
        return (int) Db::getInstance()->getValue(
            'SELECT id_customer FROM `' . _DB_PREFIX_ . 'htl_booking_detail`
             WHERE id = ' . (int) $idBooking
        );
    }

    private function getLastStay(int $idGuest): ?array
    {
        return Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'smart_guest_stay_history`
             WHERE id_guest = ' . (int) $idGuest . '
             ORDER BY checkin_date DESC LIMIT 1'
        ) ?: null;
    }

    private function recordStayHistory(int $idGuest, int $idBooking): void
    {
        $booking = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'htl_booking_detail` WHERE id = ' . (int) $idBooking
        );
        if (!$booking) {
            return;
        }

        $nights = (int) Db::getInstance()->getValue(
            'SELECT DATEDIFF("' . pSQL($booking['booking_date_to']) . '", "' . pSQL($booking['booking_date_from']) . '")'
        );

        Db::getInstance()->insert('smart_guest_stay_history', [
            'id_guest'      => (int) $idGuest,
            'id_booking'    => (int) $idBooking,
            'id_property'   => 1,
            'checkin_date'  => pSQL($booking['booking_date_from']),
            'checkout_date' => pSQL($booking['booking_date_to']),
            'nights'        => $nights,
            'total_spent'   => (float) $booking['total_paid_amount'],
        ]);
    }

    private function updateStayStats(int $idGuest): void
    {
        $stats = Db::getInstance()->getRow(
            'SELECT COUNT(*) as total_stays, SUM(nights) as total_nights,
                    SUM(total_spent) as total_spent, MIN(checkin_date) as first_stay,
                    MAX(checkout_date) as last_stay
             FROM `' . _DB_PREFIX_ . 'smart_guest_stay_history`
             WHERE id_guest = ' . (int) $idGuest
        );

        if ($stats) {
            Db::getInstance()->update('smart_guest_profile', [
                'total_stays'     => (int) $stats['total_stays'],
                'total_nights'    => (int) $stats['total_nights'],
                'total_spent'     => (float) $stats['total_spent'],
                'first_stay_date' => pSQL($stats['first_stay']),
                'last_stay_date'  => pSQL($stats['last_stay']),
                'avg_daily_spend' => $stats['total_nights'] > 0
                    ? round((float) $stats['total_spent'] / (int) $stats['total_nights'], 2)
                    : 0,
            ], 'id = ' . (int) $idGuest);
        }
    }

    public function getContent(): string
    {
        return $this->display(__FILE__, 'views/templates/admin/configuration.tpl');
    }
}
