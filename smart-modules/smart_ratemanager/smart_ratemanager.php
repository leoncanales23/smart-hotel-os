<?php
/**
 * SmartHotelOS — Módulo Smart Rate Manager
 * Grupo Smart de Administración
 *
 * Gestión dinámica de tarifas, planes tarifarios, restricciones,
 * yield management automático y conexión con channel manager.
 * Estándar Forbes 5 estrellas.
 *
 * @module      smart_ratemanager
 * @version     1.0.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Smart_Ratemanager extends Module
{
    const MODULE_VERSION = '1.0.0';

    /** Tipos de plan tarifario */
    const RATE_PLAN_TYPES = [
        'BAR'          => 'Best Available Rate',
        'ADVANCE'      => 'Tarifa Anticipada',
        'LAST_MINUTE'  => 'Última Hora',
        'NON_REFUND'   => 'No Reembolsable',
        'PACKAGE'      => 'Paquete',
        'CORPORATE'    => 'Corporativo',
        'GROUP'        => 'Grupos',
        'OTA'          => 'OTA / Canal',
        'PROMO'        => 'Promoción',
        'LOYALTY'      => 'Fidelización',
    ];

    /** Canales de distribución */
    const CHANNELS = [
        'DIRECT_WEB'   => 'Web directa',
        'DIRECT_PHONE' => 'Teléfono directo',
        'DIRECT_WALK'  => 'Walk-in',
        'BOOKING_COM'  => 'Booking.com',
        'EXPEDIA'      => 'Expedia',
        'AIRBNB'       => 'Airbnb',
        'GDS'          => 'GDS (Amadeus/Galileo)',
        'WHOLESALER'   => 'Mayorista',
        'CORPORATE'    => 'Corporativo',
        'TRAVEL_AGENT' => 'Agencia de viajes',
    ];

    /** Disparadores de yield automático */
    const YIELD_TRIGGERS = [
        'OCCUPANCY_HIGH' => 90,   // % ocupación para subir tarifa
        'OCCUPANCY_MID'  => 65,   // % ocupación tarifa media
        'OCCUPANCY_LOW'  => 40,   // % ocupación para bajar tarifa
        'DAYS_OUT_LONG'  => 45,   // días de anticipación "largo"
        'DAYS_OUT_SHORT' => 7,    // días de anticipación "corto"
        'DAYS_OUT_LAST'  => 2,    // última hora
    ];

    public function __construct()
    {
        $this->name      = 'smart_ratemanager';
        $this->tab       = 'administration';
        $this->version   = self::MODULE_VERSION;
        $this->author    = 'Smart Hotel Group';
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('Smart Rate Manager');
        $this->description = $this->l(
            'Gestión dinámica de tarifas, yield management automático y ' .
            'sincronización con canales OTA. Maximice RevPAR en tiempo real.'
        );
    }

    public function install(): bool
    {
        return parent::install()
            && $this->installSql()
            && $this->installTabs()
            && $this->registerHook(['actionSmartBookingCreated', 'displaySmartDashboardWidgets'])
            && $this->installDefaultConfig();
    }

    public function uninstall(): bool
    {
        return parent::uninstall() && $this->uninstallSql() && $this->uninstallTabs();
    }

    private function installSql(): bool
    {
        $queries = [

            // Planes tarifarios
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_rate_plan` (
                `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `id_property`     INT UNSIGNED NOT NULL DEFAULT 1,
                `code`            VARCHAR(20) NOT NULL,
                `name`            VARCHAR(100) NOT NULL,
                `plan_type`       VARCHAR(20) NOT NULL DEFAULT "BAR",
                `description`     TEXT,
                `meal_plan`       ENUM("RO","BB","HB","FB","AI") DEFAULT "BB"
                                  COMMENT "Room Only, B&B, Half Board, Full Board, All Inclusive",
                `cancellation_policy` TEXT,
                `is_refundable`   TINYINT(1) DEFAULT 1,
                `advance_booking_min` SMALLINT UNSIGNED DEFAULT 0 COMMENT "días mínimos de anticipación",
                `advance_booking_max` SMALLINT UNSIGNED DEFAULT 365,
                `min_stay`        TINYINT UNSIGNED DEFAULT 1,
                `max_stay`        TINYINT UNSIGNED DEFAULT 30,
                `active`          TINYINT(1) DEFAULT 1,
                `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uk_property_code` (`id_property`, `code`),
                INDEX `idx_type`  (`plan_type`, `active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            // Tarifas por tipo de habitación / fecha / canal
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_rate` (
                `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `id_property`     INT UNSIGNED NOT NULL DEFAULT 1,
                `id_rate_plan`    INT UNSIGNED NOT NULL,
                `id_room_type`    INT UNSIGNED NOT NULL,
                `id_channel`      VARCHAR(20) DEFAULT "DIRECT_WEB",
                `date_from`       DATE NOT NULL,
                `date_to`         DATE NOT NULL,
                `day_mon`         TINYINT(1) DEFAULT 1,
                `day_tue`         TINYINT(1) DEFAULT 1,
                `day_wed`         TINYINT(1) DEFAULT 1,
                `day_thu`         TINYINT(1) DEFAULT 1,
                `day_fri`         TINYINT(1) DEFAULT 1,
                `day_sat`         TINYINT(1) DEFAULT 1,
                `day_sun`         TINYINT(1) DEFAULT 1,
                `price_1`         DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT "1 adulto",
                `price_2`         DECIMAL(10,2) DEFAULT 0.00 COMMENT "2 adultos",
                `price_extra_adult` DECIMAL(10,2) DEFAULT 0.00,
                `price_child`     DECIMAL(10,2) DEFAULT 0.00,
                `currency`        CHAR(3) DEFAULT "CLP",
                `allotment`       SMALLINT UNSIGNED DEFAULT NULL COMMENT "Cupo asignado (NULL=sin límite)",
                `allotment_used`  SMALLINT UNSIGNED DEFAULT 0,
                `is_closed`       TINYINT(1) DEFAULT 0 COMMENT "CTA — Cerrado a llegadas",
                `is_stop_sell`    TINYINT(1) DEFAULT 0 COMMENT "Stop de ventas",
                `min_stay`        TINYINT UNSIGNED DEFAULT 1,
                `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at`      DATETIME ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_room_dates`    (`id_room_type`, `date_from`, `date_to`),
                INDEX `idx_plan_dates`    (`id_rate_plan`, `date_from`, `date_to`),
                INDEX `idx_channel_dates` (`id_channel`, `date_from`, `date_to`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            // Historial de cambios de tarifa (auditoría)
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_rate_change_log` (
                `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `id_rate`      INT UNSIGNED NOT NULL,
                `changed_by`   INT UNSIGNED NULL COMMENT "NULL = cambio automático (yield)",
                `old_price`    DECIMAL(10,2),
                `new_price`    DECIMAL(10,2),
                `reason`       VARCHAR(200),
                `changed_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_rate`    (`id_rate`),
                INDEX `idx_changed` (`changed_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            // Configuración del Yield Manager
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_yield_config` (
                `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `id_property`     INT UNSIGNED NOT NULL DEFAULT 1 UNIQUE,
                `enabled`         TINYINT(1) DEFAULT 0,
                `base_occupancy`  DECIMAL(5,2) DEFAULT 70.00,
                `increment_high`  DECIMAL(5,2) DEFAULT 20.00 COMMENT "% incremento cuando ocupación alta",
                `decrement_low`   DECIMAL(5,2) DEFAULT 15.00 COMMENT "% descuento cuando ocupación baja",
                `max_price_floor` DECIMAL(5,2) DEFAULT 0.00 COMMENT "precio mínimo (floor)",
                `max_price_ceil`  DECIMAL(5,2) DEFAULT 200.00 COMMENT "precio máximo (ceil, % sobre BAR)",
                `auto_apply`      TINYINT(1) DEFAULT 0 COMMENT "0=sugerir, 1=aplicar automáticamente",
                `notify_manager`  TINYINT(1) DEFAULT 1,
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

    private function installTabs(): bool
    {
        foreach ([
            ['AdminSmartRateManager', 'Revenue & Tarifas',     'AdminSmartHotel'],
            ['AdminSmartRatePlans',    'Planes Tarifarios',     'AdminSmartRateManager'],
            ['AdminSmartRateCalendar', 'Calendario de Tarifas', 'AdminSmartRateManager'],
            ['AdminSmartYield',        'Yield Manager',         'AdminSmartRateManager'],
            ['AdminSmartChannels',     'Canales OTA',           'AdminSmartRateManager'],
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
            'SMART_RM_DEFAULT_CURRENCY'    => 'CLP',
            'SMART_RM_YIELD_AUTO'          => 0,
            'SMART_RM_CHANNEL_MANAGER_URL' => '',
            'SMART_RM_CHANNEL_MANAGER_KEY' => '',
            'SMART_RM_PARITY_CHECK'        => 1,  // verificar paridad de precios
            'SMART_RM_LAST_MINUTE_DAYS'    => 3,
            'SMART_RM_ADVANCE_DISCOUNT'    => 10, // % descuento anticipado
        ] as $k => $v) {
            Configuration::updateValue($k, $v);
        }
        return true;
    }

    private function uninstallSql(): bool
    {
        foreach (['smart_rate_plan','smart_rate','smart_rate_change_log','smart_yield_config'] as $t) {
            Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . pSQL($t) . '`');
        }
        return true;
    }

    private function uninstallTabs(): bool
    {
        foreach (['AdminSmartRateManager','AdminSmartRatePlans','AdminSmartRateCalendar','AdminSmartYield','AdminSmartChannels'] as $c) {
            $id = (int) Tab::getIdFromClassName($c);
            if ($id) { (new Tab($id))->delete(); }
        }
        return true;
    }

    // ──────────────────────────────────────────────────────────
    //  LÓGICA DE NEGOCIO
    // ──────────────────────────────────────────────────────────

    /**
     * Obtener la mejor tarifa disponible para una combinación
     * tipo de habitación + fechas + canal + ocupantes.
     */
    public function getBestAvailableRate(
        int    $idRoomType,
        string $checkIn,
        string $checkOut,
        string $channel = 'DIRECT_WEB',
        int    $adults  = 2,
        int    $children = 0
    ): ?array {
        $nights = max(1, (int) Db::getInstance()->getValue(
            'SELECT DATEDIFF("' . pSQL($checkOut) . '", "' . pSQL($checkIn) . '")'
        ));

        $rates = Db::getInstance()->executeS(
            'SELECT r.*, rp.name as plan_name, rp.meal_plan, rp.is_refundable, rp.cancellation_policy
             FROM `' . _DB_PREFIX_ . 'smart_rate` r
             JOIN `' . _DB_PREFIX_ . 'smart_rate_plan` rp ON rp.id = r.id_rate_plan
             WHERE r.id_room_type = ' . (int) $idRoomType . '
             AND (r.id_channel = "' . pSQL($channel) . '" OR r.id_channel = "DIRECT_WEB")
             AND r.date_from <= "' . pSQL($checkIn) . '"
             AND r.date_to >= "' . pSQL($checkOut) . '"
             AND r.is_closed = 0
             AND r.is_stop_sell = 0
             AND r.min_stay <= ' . $nights . '
             AND rp.advance_booking_min <= DATEDIFF("' . pSQL($checkIn) . '", CURDATE())
             AND rp.active = 1
             ORDER BY r.price_2 ASC
             LIMIT 10'
        ) ?: [];

        if (empty($rates)) {
            return null;
        }

        // Devolver la tarifa más baja disponible
        $best = $rates[0];
        $price = $adults === 1 ? (float) $best['price_1'] : (float) $best['price_2'];
        $price += ($adults > 2) ? ($adults - 2) * (float) $best['price_extra_adult'] : 0;
        $price += $children * (float) $best['price_child'];

        return [
            'id_rate'          => $best['id'],
            'id_rate_plan'     => $best['id_rate_plan'],
            'plan_code'        => $best['code'] ?? 'BAR',
            'plan_name'        => $best['plan_name'],
            'meal_plan'        => $best['meal_plan'],
            'is_refundable'    => (bool) $best['is_refundable'],
            'price_per_night'  => round($price, 2),
            'total_price'      => round($price * $nights, 2),
            'nights'           => $nights,
            'currency'         => $best['currency'],
            'all_rates'        => $this->formatRatesForDisplay($rates, $adults, $nights),
        ];
    }

    /**
     * Actualizar tarifa de una fecha con log de auditoría.
     */
    public function updateRate(int $idRate, float $newPrice, ?int $changedBy = null, string $reason = ''): bool
    {
        $old = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'smart_rate` WHERE id = ' . (int) $idRate
        );
        if (!$old) {
            return false;
        }

        $updated = Db::getInstance()->update('smart_rate',
            ['price_2' => $newPrice, 'price_1' => $newPrice * 0.85, 'updated_at' => date('Y-m-d H:i:s')],
            'id = ' . (int) $idRate
        );

        if ($updated) {
            Db::getInstance()->insert('smart_rate_change_log', [
                'id_rate'    => (int) $idRate,
                'changed_by' => $changedBy,
                'old_price'  => (float) $old['price_2'],
                'new_price'  => $newPrice,
                'reason'     => pSQL($reason),
            ]);
        }

        return $updated;
    }

    /**
     * Motor de Yield Management.
     * Sugiere o aplica ajustes de tarifa según ocupación, anticipación y demanda.
     */
    public function runYieldEngine(int $idProperty = 1, string $targetDate = ''): array
    {
        if (!$targetDate) {
            $targetDate = date('Y-m-d', strtotime('+7 days'));
        }

        $config = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'smart_yield_config`
             WHERE id_property = ' . (int) $idProperty
        );

        if (!$config || !$config['enabled']) {
            return ['active' => false, 'message' => 'Yield Manager no está activo'];
        }

        // Calcular ocupación actual para la fecha objetivo
        $totalRooms = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'htl_room_info`
             WHERE id_hotel = ' . (int) $idProperty . ' AND is_active = 1'
        );

        $bookedRooms = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'htl_booking_detail`
             WHERE booking_status NOT IN (4,5)
             AND DATE(booking_date_from) <= "' . pSQL($targetDate) . '"
             AND DATE(booking_date_to) > "' . pSQL($targetDate) . '"'
        );

        $occupancyPct = $totalRooms > 0 ? ($bookedRooms / $totalRooms) * 100 : 0;
        $daysOut      = (int) Db::getInstance()->getValue(
            'SELECT DATEDIFF("' . pSQL($targetDate) . '", CURDATE())'
        );

        // Determinar multiplicador de precio
        $multiplier = 1.0;
        $reason     = 'Sin ajuste (ocupación normal)';

        if ($occupancyPct >= self::YIELD_TRIGGERS['OCCUPANCY_HIGH']) {
            $mult       = 1 + ($config['increment_high'] / 100);
            $multiplier = min($mult, 1 + ($config['max_price_ceil'] / 100));
            $reason     = 'Ocupación alta (' . round($occupancyPct, 1) . '%) — precio incrementado';
        } elseif ($occupancyPct <= self::YIELD_TRIGGERS['OCCUPANCY_LOW']) {
            $mult       = 1 - ($config['decrement_low'] / 100);
            $multiplier = max($mult, $config['max_price_floor'] > 0 ? $config['max_price_floor'] / 100 : 0.6);
            $reason     = 'Ocupación baja (' . round($occupancyPct, 1) . '%) — precio reducido';
        }

        // Ajuste adicional por anticipación
        if ($daysOut <= self::YIELD_TRIGGERS['DAYS_OUT_LAST'] && $occupancyPct < 85) {
            $multiplier *= 0.90; // Descuento adicional última hora si hay disponibilidad
            $reason .= ' + descuento última hora';
        } elseif ($daysOut >= self::YIELD_TRIGGERS['DAYS_OUT_LONG']) {
            $multiplier *= 1.05; // Ligero incremento para anticipadas
        }

        // Obtener tarifas actuales BAR y calcular sugerencias
        $barRates = Db::getInstance()->executeS(
            'SELECT r.*, rp.name as plan_name
             FROM `' . _DB_PREFIX_ . 'smart_rate` r
             JOIN `' . _DB_PREFIX_ . 'smart_rate_plan` rp ON rp.id = r.id_rate_plan AND rp.plan_type = "BAR"
             WHERE r.date_from <= "' . pSQL($targetDate) . '"
             AND r.date_to >= "' . pSQL($targetDate) . '"
             ORDER BY r.id_room_type ASC'
        ) ?: [];

        $suggestions = [];
        foreach ($barRates as $rate) {
            $currentPrice  = (float) $rate['price_2'];
            $suggestedPrice = round($currentPrice * $multiplier, -3); // redondear a miles (CLP)

            if (abs($suggestedPrice - $currentPrice) >= 1000) { // solo si cambia más de $1.000 CLP
                $suggestions[] = [
                    'id_rate'        => $rate['id'],
                    'id_room_type'   => $rate['id_room_type'],
                    'plan_name'      => $rate['plan_name'],
                    'current_price'  => $currentPrice,
                    'suggested_price'=> $suggestedPrice,
                    'change_pct'     => round(($suggestedPrice - $currentPrice) / $currentPrice * 100, 1),
                    'reason'         => $reason,
                ];

                // Aplicar automáticamente si está configurado
                if ((int) $config['auto_apply']) {
                    $this->updateRate($rate['id'], $suggestedPrice, null, 'Yield automático: ' . $reason);
                }
            }
        }

        return [
            'active'         => true,
            'target_date'    => $targetDate,
            'days_out'       => $daysOut,
            'occupancy_pct'  => round($occupancyPct, 1),
            'multiplier'     => round($multiplier, 3),
            'auto_applied'   => (bool) $config['auto_apply'],
            'suggestions'    => $suggestions,
            'reason'         => $reason,
        ];
    }

    /**
     * Verificar paridad de precios: misma tarifa en todos los canales.
     * Importante para evitar penalizaciones de Booking.com y Expedia.
     */
    public function checkRateParity(int $idRoomType, string $date): array
    {
        $rates = Db::getInstance()->executeS(
            'SELECT id_channel, price_2, is_stop_sell
             FROM `' . _DB_PREFIX_ . 'smart_rate`
             WHERE id_room_type = ' . (int) $idRoomType . '
             AND date_from <= "' . pSQL($date) . '"
             AND date_to >= "' . pSQL($date) . '"
             AND is_stop_sell = 0'
        ) ?: [];

        $prices = array_column($rates, 'price_2', 'id_channel');
        $min    = min($prices ?: [0]);
        $max    = max($prices ?: [0]);
        $parity = ($max - $min) / max($min, 1) * 100;

        return [
            'date'        => $date,
            'prices'      => $prices,
            'min_price'   => $min,
            'max_price'   => $max,
            'disparity_pct' => round($parity, 1),
            'parity_ok'   => $parity < 5, // < 5% de diferencia = OK
            'alerts'      => $parity >= 5 ? ['Paridad de precio comprometida — diferencia del ' . round($parity, 1) . '%'] : [],
        ];
    }

    private function formatRatesForDisplay(array $rates, int $adults, int $nights): array
    {
        return array_map(function ($r) use ($adults, $nights) {
            $price = $adults === 1 ? (float) $r['price_1'] : (float) $r['price_2'];
            return [
                'id_rate_plan'  => $r['id_rate_plan'],
                'plan_name'     => $r['plan_name'],
                'meal_plan'     => $r['meal_plan'],
                'is_refundable' => (bool) $r['is_refundable'],
                'price_per_night' => round($price, 2),
                'total_price'   => round($price * $nights, 2),
                'currency'      => $r['currency'],
            ];
        }, $rates);
    }

    public function getContent(): string
    {
        return $this->display(__FILE__, 'views/templates/admin/configuration.tpl');
    }
}
