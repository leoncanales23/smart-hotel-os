<?php
/**
 * SmartHotelOS — Módulo Smart Restaurant (F&B)
 * Grupo Smart de Administración
 *
 * POS completo para restaurante, bar y servicio a la habitación.
 * Gestión de mesas, comandas digitales, control de turno,
 * cargos directos a la habitación y reportes de F&B.
 *
 * @module  smart_restaurant
 * @version 1.0.0
 */

if (!defined('_PS_VERSION_')) { exit; }

class Smart_Restaurant extends Module
{
    const MODULE_VERSION = '1.0.0';

    const OUTLET_TYPES = [
        'RESTAURANT' => 'Restaurante',
        'BAR'        => 'Bar / Lounge',
        'POOL_BAR'   => 'Bar de piscina',
        'ROOM_SVC'   => 'Servicio a la habitación',
        'BANQUET'    => 'Banquetes / Eventos',
        'MINIBAR'    => 'Minibar',
    ];

    const ORDER_STATUS = [
        'OPEN'       => 'Abierta',
        'SENT'       => 'Enviada a cocina',
        'READY'      => 'Lista para servir',
        'SERVED'     => 'Servida',
        'CLOSED'     => 'Cerrada / Pagada',
        'VOID'       => 'Anulada',
    ];

    const TABLE_STATUS = [
        'AVAILABLE'  => 'Disponible',
        'OCCUPIED'   => 'Ocupada',
        'RESERVED'   => 'Reservada',
        'CLEANING'   => 'En limpieza',
        'BLOCKED'    => 'Bloqueada',
    ];

    const PAYMENT_METHODS = [
        'CASH'          => 'Efectivo',
        'CARD'          => 'Tarjeta',
        'ROOM_CHARGE'   => 'Cargo a habitación',
        'VOUCHER'       => 'Voucher',
        'COMPLIMENTARY' => 'Cortesía',
    ];

    public function __construct()
    {
        $this->name      = 'smart_restaurant';
        $this->tab       = 'administration';
        $this->version   = self::MODULE_VERSION;
        $this->author    = 'Smart Hotel Group';
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('Smart Restaurant & F&B');
        $this->description = $this->l(
            'POS completo para restaurante, bar y room service. Comandas digitales, ' .
            'gestión de mesas, cargos a habitación y reportes F&B 5 estrellas.'
        );
    }

    public function install(): bool
    {
        return parent::install()
            && $this->installSql()
            && $this->installTabs()
            && $this->registerHook([
                'displaySmartDashboardWidgets',
                'actionSmartCheckOut',        // al checkout, verificar cargos pendientes
                'actionSmartRoomServiceRequest',
            ])
            && $this->installDefaultConfig()
            && $this->installSampleData();
    }

    public function uninstall(): bool
    {
        return parent::uninstall() && $this->uninstallSql() && $this->uninstallTabs();
    }

    private function installSql(): bool
    {
        $queries = [

            // Outlets (puntos de venta: restaurante, bar, room service)
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_fb_outlet` (
                `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `id_property`   INT UNSIGNED NOT NULL DEFAULT 1,
                `name`          VARCHAR(100) NOT NULL,
                `outlet_type`   VARCHAR(20) NOT NULL DEFAULT "RESTAURANT",
                `code`          VARCHAR(10) NOT NULL,
                `floor`         TINYINT UNSIGNED DEFAULT 1,
                `capacity`      SMALLINT UNSIGNED DEFAULT 0,
                `opening_time`  TIME DEFAULT "07:00:00",
                `closing_time`  TIME DEFAULT "23:00:00",
                `active`        TINYINT(1) DEFAULT 1,
                UNIQUE KEY `uk_property_code` (`id_property`, `code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            // Mesas
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_fb_table` (
                `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `id_outlet`     INT UNSIGNED NOT NULL,
                `table_number`  VARCHAR(10) NOT NULL,
                `capacity`      TINYINT UNSIGNED DEFAULT 4,
                `status`        VARCHAR(20) DEFAULT "AVAILABLE",
                `current_order` INT UNSIGNED NULL,
                `section`       VARCHAR(50) COMMENT "Interior, terraza, barra, etc.",
                `pos_x`         SMALLINT DEFAULT 0 COMMENT "Posición X en el mapa del restaurante",
                `pos_y`         SMALLINT DEFAULT 0 COMMENT "Posición Y en el mapa del restaurante",
                `active`        TINYINT(1) DEFAULT 1,
                UNIQUE KEY `uk_outlet_table` (`id_outlet`, `table_number`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            // Categorías del menú
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_fb_menu_category` (
                `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `id_outlet`     INT UNSIGNED NULL COMMENT "NULL = aplica a todos los outlets",
                `name`          VARCHAR(100) NOT NULL,
                `name_en`       VARCHAR(100),
                `sort_order`    TINYINT UNSIGNED DEFAULT 0,
                `active`        TINYINT(1) DEFAULT 1,
                `available_from` TIME NULL,
                `available_to`  TIME NULL,
                `image_url`     VARCHAR(500)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            // Ítems del menú
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_fb_menu_item` (
                `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `id_category`     INT UNSIGNED NOT NULL,
                `id_outlet`       INT UNSIGNED NULL,
                `name`            VARCHAR(200) NOT NULL,
                `name_en`         VARCHAR(200),
                `description`     TEXT,
                `price`           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `price_room_svc`  DECIMAL(10,2) DEFAULT NULL COMMENT "Precio diferencial room service",
                `cost`            DECIMAL(10,2) DEFAULT 0.00 COMMENT "Costo estimado (para margen)",
                `sku`             VARCHAR(50),
                `unit`            VARCHAR(20) DEFAULT "porción",
                `prep_time_min`   TINYINT UNSIGNED DEFAULT 15 COMMENT "Tiempo de preparación en minutos",
                `is_vegan`        TINYINT(1) DEFAULT 0,
                `is_vegetarian`   TINYINT(1) DEFAULT 0,
                `is_gluten_free`  TINYINT(1) DEFAULT 0,
                `allergens`       JSON,
                `tags`            JSON COMMENT "popular, chef_recommend, new, seasonal",
                `image_url`       VARCHAR(500),
                `available`       TINYINT(1) DEFAULT 1,
                `active`          TINYINT(1) DEFAULT 1,
                `sort_order`      TINYINT UNSIGNED DEFAULT 0,
                INDEX `idx_category` (`id_category`, `active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            // Órdenes (comandas)
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_fb_order` (
                `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `id_outlet`       INT UNSIGNED NOT NULL,
                `id_table`        INT UNSIGNED NULL,
                `id_booking`      INT UNSIGNED NULL COMMENT "Para room service / cargo a habitación",
                `id_room`         INT UNSIGNED NULL,
                `order_number`    VARCHAR(20) NOT NULL UNIQUE,
                `status`          VARCHAR(20) DEFAULT "OPEN",
                `covers`          TINYINT UNSIGNED DEFAULT 1 COMMENT "Número de comensales",
                `subtotal`        DECIMAL(10,2) DEFAULT 0.00,
                `discount`        DECIMAL(10,2) DEFAULT 0.00,
                `tax`             DECIMAL(10,2) DEFAULT 0.00,
                `service_charge`  DECIMAL(10,2) DEFAULT 0.00,
                `total`           DECIMAL(10,2) DEFAULT 0.00,
                `payment_method`  VARCHAR(20) NULL,
                `payment_ref`     VARCHAR(100) NULL,
                `id_waiter`       INT UNSIGNED NULL,
                `id_cashier`      INT UNSIGNED NULL,
                `notes`           TEXT,
                `opened_at`       DATETIME DEFAULT CURRENT_TIMESTAMP,
                `sent_at`         DATETIME NULL,
                `closed_at`       DATETIME NULL,
                INDEX `idx_outlet_status` (`id_outlet`, `status`),
                INDEX `idx_table`         (`id_table`),
                INDEX `idx_booking`       (`id_booking`),
                INDEX `idx_date`          (`opened_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            // Líneas de orden (ítems de la comanda)
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_fb_order_line` (
                `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `id_order`        INT UNSIGNED NOT NULL,
                `id_item`         INT UNSIGNED NOT NULL,
                `item_name`       VARCHAR(200) NOT NULL COMMENT "Snapshot del nombre al momento del pedido",
                `unit_price`      DECIMAL(10,2) NOT NULL,
                `quantity`        DECIMAL(5,2) NOT NULL DEFAULT 1,
                `discount_pct`    DECIMAL(5,2) DEFAULT 0.00,
                `line_total`      DECIMAL(10,2) NOT NULL,
                `modifiers`       JSON COMMENT "Modificaciones: sin cebolla, punto cocción, etc.",
                `notes`           VARCHAR(300),
                `status`          ENUM("ordered","in_prep","ready","served","voided") DEFAULT "ordered",
                `voided_reason`   VARCHAR(200) NULL,
                `added_at`        DATETIME DEFAULT CURRENT_TIMESTAMP,
                `served_at`       DATETIME NULL,
                INDEX `idx_order` (`id_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            // Turnos de caja
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_fb_shift` (
                `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `id_outlet`       INT UNSIGNED NOT NULL,
                `id_cashier`      INT UNSIGNED NOT NULL,
                `opening_float`   DECIMAL(10,2) DEFAULT 0.00,
                `closing_float`   DECIMAL(10,2) NULL,
                `total_sales`     DECIMAL(12,2) DEFAULT 0.00,
                `total_covers`    SMALLINT UNSIGNED DEFAULT 0,
                `opened_at`       DATETIME DEFAULT CURRENT_TIMESTAMP,
                `closed_at`       DATETIME NULL,
                `notes`           TEXT,
                INDEX `idx_outlet_date` (`id_outlet`, `opened_at`)
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
            ['AdminSmartRestaurant',    'Restaurante & F&B',       'AdminSmartHotel'],
            ['AdminSmartFbFloorPlan',   'Plano de Mesas',          'AdminSmartRestaurant'],
            ['AdminSmartFbOrders',      'Comandas Activas',         'AdminSmartRestaurant'],
            ['AdminSmartFbMenu',        'Menú Digital',            'AdminSmartRestaurant'],
            ['AdminSmartRoomService',   'Servicio a la Habitación', 'AdminSmartRestaurant'],
            ['AdminSmartFbReports',     'Reportes F&B',            'AdminSmartRestaurant'],
            ['AdminSmartFbShifts',      'Turnos de Caja',          'AdminSmartRestaurant'],
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
            'SMART_FB_SERVICE_CHARGE_PCT' => 10,    // % cargo por servicio
            'SMART_FB_TAX_PCT'            => 19,    // IVA Chile
            'SMART_FB_ROOM_SVC_FEE'       => 2500,  // Cargo room service en CLP
            'SMART_FB_ROOM_SVC_HOURS'     => '00:00-23:59',
            'SMART_FB_ORDER_RECEIPT_PRINT' => 1,
            'SMART_FB_KITCHEN_DISPLAY'    => 0,
            'SMART_FB_AUTO_CLOSE_MINUTES' => 0,     // 0 = manual
        ] as $k => $v) {
            Configuration::updateValue($k, $v);
        }
        return true;
    }

    private function installSampleData(): bool
    {
        // Crear outlet de muestra si no existe
        $exists = Db::getInstance()->getValue(
            'SELECT id FROM `' . _DB_PREFIX_ . 'smart_fb_outlet` WHERE id_property = 1 LIMIT 1'
        );
        if ($exists) { return true; }

        Db::getInstance()->insert('smart_fb_outlet', [
            'id_property'  => 1,
            'name'         => 'Restaurante Smart',
            'outlet_type'  => 'RESTAURANT',
            'code'         => 'REST01',
            'floor'        => 1,
            'capacity'     => 80,
            'opening_time' => '07:00:00',
            'closing_time' => '23:00:00',
        ]);

        $outletId = (int) Db::getInstance()->Insert_ID();

        Db::getInstance()->insert('smart_fb_outlet', [
            'id_property'  => 1,
            'name'         => 'Room Service',
            'outlet_type'  => 'ROOM_SVC',
            'code'         => 'RSV01',
            'floor'        => 0,
            'capacity'     => 0,
            'opening_time' => '00:00:00',
            'closing_time' => '23:59:00',
        ]);

        return true;
    }

    private function uninstallSql(): bool
    {
        foreach (['smart_fb_outlet','smart_fb_table','smart_fb_menu_category',
                  'smart_fb_menu_item','smart_fb_order','smart_fb_order_line','smart_fb_shift'] as $t) {
            Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . pSQL($t) . '`');
        }
        return true;
    }

    private function uninstallTabs(): bool
    {
        foreach (['AdminSmartRestaurant','AdminSmartFbFloorPlan','AdminSmartFbOrders',
                  'AdminSmartFbMenu','AdminSmartRoomService','AdminSmartFbReports','AdminSmartFbShifts'] as $c) {
            $id = (int) Tab::getIdFromClassName($c);
            if ($id) { (new Tab($id))->delete(); }
        }
        return true;
    }

    // ──────────────────────────────────────────────────────────
    //  POS — LÓGICA DE NEGOCIO
    // ──────────────────────────────────────────────────────────

    /**
     * Abrir una nueva comanda en una mesa.
     */
    public function openOrder(int $idOutlet, int $idTable, int $covers, int $idWaiter): array
    {
        // Verificar que la mesa esté disponible
        $table = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'smart_fb_table` WHERE id = ' . (int) $idTable
        );

        if (!$table) {
            return ['success' => false, 'message' => 'Mesa no encontrada'];
        }
        if ($table['status'] === 'OCCUPIED') {
            return ['success' => false, 'message' => 'La mesa ya está ocupada (orden #' . $table['current_order'] . ')'];
        }

        $orderNumber = $this->generateOrderNumber($idOutlet);

        Db::getInstance()->insert('smart_fb_order', [
            'id_outlet'    => (int) $idOutlet,
            'id_table'     => (int) $idTable,
            'order_number' => pSQL($orderNumber),
            'status'       => 'OPEN',
            'covers'       => (int) $covers,
            'id_waiter'    => (int) $idWaiter,
            'opened_at'    => date('Y-m-d H:i:s'),
        ]);

        $idOrder = (int) Db::getInstance()->Insert_ID();

        // Marcar mesa como ocupada
        Db::getInstance()->update('smart_fb_table',
            ['status' => 'OCCUPIED', 'current_order' => $idOrder],
            'id = ' . (int) $idTable
        );

        return [
            'success'      => true,
            'id_order'     => $idOrder,
            'order_number' => $orderNumber,
        ];
    }

    /**
     * Añadir ítem a una comanda.
     */
    public function addItemToOrder(int $idOrder, int $idItem, float $qty = 1, array $modifiers = [], string $notes = ''): array
    {
        $order = $this->getOrder($idOrder);
        if (!$order) {
            return ['success' => false, 'message' => 'Comanda no encontrada'];
        }
        if ($order['status'] === 'CLOSED' || $order['status'] === 'VOID') {
            return ['success' => false, 'message' => 'La comanda está cerrada'];
        }

        $item = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'smart_fb_menu_item` WHERE id = ' . (int) $idItem
        );
        if (!$item || !$item['available']) {
            return ['success' => false, 'message' => 'Ítem no disponible'];
        }

        // Precio: usar room service si aplica
        $unitPrice = ($order['id_room'] && $item['price_room_svc'])
            ? (float) $item['price_room_svc']
            : (float) $item['price'];

        $lineTotal = round($unitPrice * $qty, 2);

        Db::getInstance()->insert('smart_fb_order_line', [
            'id_order'   => (int) $idOrder,
            'id_item'    => (int) $idItem,
            'item_name'  => pSQL($item['name']),
            'unit_price' => $unitPrice,
            'quantity'   => $qty,
            'line_total' => $lineTotal,
            'modifiers'  => pSQL(json_encode($modifiers)),
            'notes'      => pSQL($notes),
            'status'     => 'ordered',
            'added_at'   => date('Y-m-d H:i:s'),
        ]);

        $this->recalculateOrder($idOrder);

        return [
            'success'    => true,
            'id_line'    => (int) Db::getInstance()->Insert_ID(),
            'line_total' => $lineTotal,
        ];
    }

    /**
     * Enviar comanda a cocina.
     */
    public function sendToKitchen(int $idOrder): array
    {
        $lines = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'smart_fb_order_line`
             WHERE id_order = ' . (int) $idOrder . ' AND status = "ordered"'
        );

        if (empty($lines)) {
            return ['success' => false, 'message' => 'No hay ítems pendientes de enviar'];
        }

        Db::getInstance()->update('smart_fb_order_line',
            ['status' => 'in_prep'],
            'id_order = ' . (int) $idOrder . ' AND status = "ordered"'
        );

        Db::getInstance()->update('smart_fb_order',
            ['status' => 'SENT', 'sent_at' => date('Y-m-d H:i:s')],
            'id = ' . (int) $idOrder
        );

        // Disparar hook para KDS (Kitchen Display System)
        Hook::exec('actionSmartFbKitchenOrder', [
            'id_order' => $idOrder,
            'lines'    => $lines,
        ]);

        return ['success' => true, 'items_sent' => count($lines)];
    }

    /**
     * Cerrar y pagar una comanda.
     */
    public function closeOrder(int $idOrder, string $paymentMethod, ?int $idBooking = null): array
    {
        $order = $this->getOrder($idOrder);
        if (!$order || $order['status'] === 'CLOSED') {
            return ['success' => false, 'message' => 'Comanda no válida o ya cerrada'];
        }

        // Si es cargo a habitación, verificar que la reserva esté activa
        if ($paymentMethod === 'ROOM_CHARGE') {
            if (!$idBooking) {
                return ['success' => false, 'message' => 'Se requiere número de habitación para cargo directo'];
            }
            $this->chargeToRoom($idOrder, $idBooking, (float) $order['total']);
        }

        Db::getInstance()->update('smart_fb_order', [
            'status'         => 'CLOSED',
            'payment_method' => pSQL($paymentMethod),
            'id_booking'     => $idBooking,
            'closed_at'      => date('Y-m-d H:i:s'),
        ], 'id = ' . (int) $idOrder);

        // Liberar mesa
        if ($order['id_table']) {
            Db::getInstance()->update('smart_fb_table',
                ['status' => 'CLEANING', 'current_order' => null],
                'id = ' . (int) $order['id_table']
            );
        }

        return [
            'success'   => true,
            'total'     => $order['total'],
            'receipt'   => $this->generateReceiptData($idOrder),
        ];
    }

    /**
     * Añadir cargo de F&B directamente al folio de la habitación.
     */
    public function chargeToRoom(int $idOrder, int $idBooking, float $amount): bool
    {
        return (bool) Db::getInstance()->insert('smart_room_charge', [
            'id_booking'   => (int) $idBooking,
            'charge_type'  => 'FB',
            'description'  => 'Restaurante / F&B — Orden #' . $idOrder,
            'amount'       => $amount,
            'id_reference' => (int) $idOrder,
            'charged_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    // ──────────────────────────────────────────────────────────
    //  REPORTES F&B
    // ──────────────────────────────────────────────────────────

    /**
     * Reporte de ventas del día por outlet.
     */
    public function getDailyReport(int $idProperty, string $date): array
    {
        $outlets = Db::getInstance()->executeS(
            'SELECT o.id, o.name, o.outlet_type,
                    COUNT(ord.id) as total_orders,
                    SUM(ord.covers) as total_covers,
                    SUM(ord.subtotal) as subtotal,
                    SUM(ord.total) as total_revenue,
                    AVG(ord.total) as avg_check
             FROM `' . _DB_PREFIX_ . 'smart_fb_outlet` o
             LEFT JOIN `' . _DB_PREFIX_ . 'smart_fb_order` ord
               ON ord.id_outlet = o.id AND DATE(ord.opened_at) = "' . pSQL($date) . '"
               AND ord.status = "CLOSED"
             WHERE o.id_property = ' . (int) $idProperty . '
             GROUP BY o.id'
        ) ?: [];

        $topItems = Db::getInstance()->executeS(
            'SELECT l.item_name, SUM(l.quantity) as qty_sold, SUM(l.line_total) as revenue
             FROM `' . _DB_PREFIX_ . 'smart_fb_order_line` l
             JOIN `' . _DB_PREFIX_ . 'smart_fb_order` o ON o.id = l.id_order
             WHERE DATE(o.opened_at) = "' . pSQL($date) . '"
             AND o.status = "CLOSED" AND l.status != "voided"
             GROUP BY l.id_item, l.item_name
             ORDER BY qty_sold DESC LIMIT 10'
        ) ?: [];

        $totalRevenue = array_sum(array_column($outlets, 'total_revenue'));

        return [
            'date'          => $date,
            'total_revenue' => round($totalRevenue, 2),
            'total_orders'  => array_sum(array_column($outlets, 'total_orders')),
            'total_covers'  => array_sum(array_column($outlets, 'total_covers')),
            'by_outlet'     => $outlets,
            'top_items'     => $topItems,
        ];
    }

    // ── Helpers ──────────────────────────────────────────────

    private function getOrder(int $id): ?array
    {
        return Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'smart_fb_order` WHERE id = ' . (int) $id
        ) ?: null;
    }

    private function recalculateOrder(int $idOrder): void
    {
        $subtotal = (float) Db::getInstance()->getValue(
            'SELECT SUM(line_total) FROM `' . _DB_PREFIX_ . 'smart_fb_order_line`
             WHERE id_order = ' . (int) $idOrder . ' AND status != "voided"'
        );

        $taxPct     = (float) Configuration::get('SMART_FB_TAX_PCT') / 100;
        $svcPct     = (float) Configuration::get('SMART_FB_SERVICE_CHARGE_PCT') / 100;
        $svcCharge  = round($subtotal * $svcPct, 2);
        $tax        = round($subtotal * $taxPct, 2);
        $total      = $subtotal + $svcCharge; // IVA incluido en Chile (precio ya incluye IVA)

        Db::getInstance()->update('smart_fb_order', [
            'subtotal'       => round($subtotal, 2),
            'service_charge' => $svcCharge,
            'tax'            => $tax,
            'total'          => round($total, 2),
        ], 'id = ' . (int) $idOrder);
    }

    private function generateOrderNumber(int $idOutlet): string
    {
        $today   = date('Ymd');
        $lastSeq = Db::getInstance()->getValue(
            'SELECT MAX(CAST(SUBSTRING(order_number, -4) AS UNSIGNED))
             FROM `' . _DB_PREFIX_ . 'smart_fb_order`
             WHERE id_outlet = ' . (int) $idOutlet . '
             AND DATE(opened_at) = "' . date('Y-m-d') . '"'
        );
        return $idOutlet . '-' . $today . '-' . str_pad(((int) $lastSeq) + 1, 4, '0', STR_PAD_LEFT);
    }

    private function generateReceiptData(int $idOrder): array
    {
        $order = $this->getOrder($idOrder);
        $lines = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'smart_fb_order_line`
             WHERE id_order = ' . (int) $idOrder . ' AND status != "voided"
             ORDER BY added_at ASC'
        ) ?: [];

        return [
            'order'  => $order,
            'lines'  => $lines,
            'hotel'  => Configuration::get('PS_SHOP_NAME'),
            'issued' => date('Y-m-d H:i:s'),
        ];
    }

    public function getContent(): string
    {
        return $this->display(__FILE__, 'views/templates/admin/configuration.tpl');
    }
}
