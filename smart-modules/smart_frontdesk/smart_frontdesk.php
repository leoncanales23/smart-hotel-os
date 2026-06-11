<?php
/**
 * SmartHotelOS — Módulo Smart Front Desk
 * Grupo Smart de Administración
 *
 * Check-in / Check-out digital, gestión de llegadas y salidas,
 * asignación de habitaciones y comunicación con huéspedes.
 *
 * @module      smart_frontdesk
 * @version     1.0.0
 * @author      Smart Hotel Group Dev Team
 * @license     Propietario — Todos los derechos reservados
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Smart_Frontdesk extends Module
{
    /** @var string Versión del módulo */
    const MODULE_VERSION = '1.0.0';

    /** @var array Estados de reserva */
    const BOOKING_STATUS = [
        'CONFIRMED'   => 1,
        'CHECKED_IN'  => 2,
        'CHECKED_OUT' => 3,
        'CANCELLED'   => 4,
        'NO_SHOW'     => 5,
        'IN_HOUSE'    => 6,
    ];

    /** @var array Tipos de documento de identidad aceptados */
    const ID_TYPES = [
        'RUT'       => 'RUT (Chile)',
        'PASSPORT'  => 'Pasaporte',
        'DNI'       => 'DNI',
        'OTHER'     => 'Otro',
    ];

    public function __construct()
    {
        $this->name    = 'smart_frontdesk';
        $this->tab     = 'administration';
        $this->version = self::MODULE_VERSION;
        $this->author  = 'Smart Hotel Group';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7.0.0',
            'max' => _PS_VERSION_,
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Smart Front Desk');
        $this->description = $this->l(
            'Sistema completo de recepción: check-in/out digital, asignación de habitaciones, ' .
            'comunicación con huéspedes y gestión de llegadas/salidas. Estándar 5 estrellas.'
        );
    }

    // ──────────────────────────────────────────────────────────
    //  INSTALACIÓN
    // ──────────────────────────────────────────────────────────

    public function install(): bool
    {
        return parent::install()
            && $this->installSql()
            && $this->installTabs()
            && $this->installHooks()
            && $this->installDefaultConfig();
    }

    public function uninstall(): bool
    {
        return parent::uninstall()
            && $this->uninstallSql()
            && $this->uninstallTabs();
    }

    private function installSql(): bool
    {
        $sqlFile = dirname(__FILE__) . '/sql/install.php';
        if (!file_exists($sqlFile)) {
            return false;
        }
        require_once $sqlFile;
        return true;
    }

    private function installTabs(): bool
    {
        $tabs = [
            [
                'class_name' => 'AdminSmartFrontDesk',
                'name'       => 'Front Desk',
                'parent'     => 'AdminSmartHotel',
                'icon'       => 'hotel',
            ],
            [
                'class_name' => 'AdminSmartArrivals',
                'name'       => 'Llegadas del Día',
                'parent'     => 'AdminSmartFrontDesk',
                'icon'       => 'login',
            ],
            [
                'class_name' => 'AdminSmartDepartures',
                'name'       => 'Salidas del Día',
                'parent'     => 'AdminSmartFrontDesk',
                'icon'       => 'logout',
            ],
            [
                'class_name' => 'AdminSmartInHouse',
                'name'       => 'Huéspedes en Casa',
                'parent'     => 'AdminSmartFrontDesk',
                'icon'       => 'people',
            ],
        ];

        foreach ($tabs as $tabData) {
            $tab = new Tab();
            $tab->active = 1;
            $tab->class_name = $tabData['class_name'];
            $tab->module = $this->name;
            $tab->id_parent = (int) Tab::getIdFromClassName($tabData['parent']);
            foreach (Language::getLanguages() as $lang) {
                $tab->name[$lang['id_lang']] = $tabData['name'];
            }
            if (!$tab->add()) {
                return false;
            }
        }

        return true;
    }

    private function installHooks(): bool
    {
        return $this->registerHook([
            'actionBookingStatusChange',
            'actionSmartCheckIn',
            'actionSmartCheckOut',
            'displaySmartDashboardWidgets',
            'displaySmartFrontDeskAlerts',
        ]);
    }

    private function installDefaultConfig(): bool
    {
        $configs = [
            'SMART_FD_EARLY_CHECKIN_FEE'     => 0,
            'SMART_FD_LATE_CHECKOUT_FEE'     => 0,
            'SMART_FD_DIGITAL_KEY_ENABLED'   => 0,
            'SMART_FD_WHATSAPP_ENABLED'      => 0,
            'SMART_FD_KIOSK_ENABLED'         => 0,
            'SMART_FD_AUTO_ASSIGN_ROOMS'     => 1,
            'SMART_FD_CHECKIN_TIME'          => '15:00',
            'SMART_FD_CHECKOUT_TIME'         => '12:00',
            'SMART_FD_NOTIFY_HOUSEKEEPING'   => 1,
            'SMART_FD_PRE_CHECKIN_HOURS'     => 24,
        ];

        foreach ($configs as $key => $value) {
            if (!Configuration::updateValue($key, $value)) {
                return false;
            }
        }

        return true;
    }

    private function uninstallSql(): bool
    {
        $tables = [
            'smart_checkin_log',
            'smart_checkout_log',
            'smart_room_assignment',
            'smart_guest_document',
            'smart_digital_key',
            'smart_fd_note',
        ];

        foreach ($tables as $table) {
            Db::getInstance()->execute(
                'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . pSQL($table) . '`'
            );
        }

        return true;
    }

    private function uninstallTabs(): bool
    {
        $tabs = [
            'AdminSmartFrontDesk',
            'AdminSmartArrivals',
            'AdminSmartDepartures',
            'AdminSmartInHouse',
        ];

        foreach ($tabs as $className) {
            $idTab = (int) Tab::getIdFromClassName($className);
            if ($idTab) {
                $tab = new Tab($idTab);
                $tab->delete();
            }
        }

        return true;
    }

    // ──────────────────────────────────────────────────────────
    //  HOOKS
    // ──────────────────────────────────────────────────────────

    /**
     * Se dispara cuando cambia el estado de una reserva.
     * Notifica a housekeeping cuando el huésped hace check-out.
     */
    public function hookActionBookingStatusChange(array $params): void
    {
        if (!isset($params['booking']) || !isset($params['new_status'])) {
            return;
        }

        $booking   = $params['booking'];
        $newStatus = (int) $params['new_status'];

        if ($newStatus === self::BOOKING_STATUS['CHECKED_OUT']) {
            // Notificar a Housekeeping que la habitación necesita limpieza
            Hook::exec('actionSmartRoomNeedsHousekeeping', [
                'id_room'    => $booking->id_room,
                'id_booking' => $booking->id,
                'priority'   => 'HIGH',
                'type'       => 'CHECKOUT',
            ]);
        }

        if ($newStatus === self::BOOKING_STATUS['CHECKED_IN']) {
            // Enviar mensaje de bienvenida al huésped
            if (Configuration::get('SMART_FD_WHATSAPP_ENABLED')) {
                $this->sendWelcomeMessage($booking);
            }
        }
    }

    /**
     * Widgets para el dashboard ejecutivo.
     */
    public function hookDisplaySmartDashboardWidgets(): string
    {
        $today = date('Y-m-d');

        $this->context->smarty->assign([
            'arrivals_today'   => $this->countArrivalsToday($today),
            'departures_today' => $this->countDeparturesToday($today),
            'in_house'         => $this->countInHouseGuests(),
            'available_rooms'  => $this->countAvailableRooms($today),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/dashboard_widget.tpl');
    }

    // ──────────────────────────────────────────────────────────
    //  LÓGICA DE NEGOCIO
    // ──────────────────────────────────────────────────────────

    /**
     * Procesa el check-in de un huésped.
     *
     * @param int   $idBooking   ID de la reserva
     * @param int   $idRoom      ID de la habitación asignada
     * @param array $guestData   Datos del documento de identidad
     * @param int   $idAgent     ID del agente de recepción
     * @return array             ['success' => bool, 'message' => string, 'data' => array]
     */
    public function processCheckIn(
        int $idBooking,
        int $idRoom,
        array $guestData,
        int $idAgent
    ): array {
        try {
            // Validar que la reserva exista y esté confirmada
            $booking = new HotelBookingDetail($idBooking);
            if (!Validate::isLoadedObject($booking)) {
                return ['success' => false, 'message' => 'Reserva no encontrada'];
            }

            if ((int) $booking->booking_status !== self::BOOKING_STATUS['CONFIRMED']) {
                return ['success' => false, 'message' => 'La reserva no está en estado confirmado'];
            }

            // Validar que la habitación esté disponible
            if (!$this->isRoomAvailable($idRoom)) {
                return ['success' => false, 'message' => 'La habitación no está disponible'];
            }

            // Asignar habitación
            $this->assignRoom($idBooking, $idRoom);

            // Guardar documento de identidad
            $this->saveGuestDocument($idBooking, $guestData);

            // Actualizar estado de la reserva
            $booking->booking_status = self::BOOKING_STATUS['CHECKED_IN'];
            $booking->checkin_date   = date('Y-m-d H:i:s');
            $booking->update();

            // Registrar en log
            $this->logCheckIn($idBooking, $idRoom, $idAgent);

            // Disparar hook
            Hook::exec('actionSmartCheckIn', [
                'id_booking' => $idBooking,
                'id_room'    => $idRoom,
                'id_agent'   => $idAgent,
            ]);

            return [
                'success' => true,
                'message' => 'Check-in realizado exitosamente',
                'data'    => [
                    'id_booking'  => $idBooking,
                    'room_number' => $this->getRoomNumber($idRoom),
                    'checkin_at'  => date('Y-m-d H:i:s'),
                ],
            ];

        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'SmartFrontDesk::processCheckIn ERROR: ' . $e->getMessage(),
                3, // Error level
                null,
                'SmartFrontDesk',
                $idBooking
            );
            return ['success' => false, 'message' => 'Error interno al procesar check-in'];
        }
    }

    /**
     * Procesa el check-out de un huésped.
     */
    public function processCheckOut(int $idBooking, int $idAgent): array
    {
        try {
            $booking = new HotelBookingDetail($idBooking);
            if (!Validate::isLoadedObject($booking)) {
                return ['success' => false, 'message' => 'Reserva no encontrada'];
            }

            // Verificar que no haya cargos pendientes
            $pendingCharges = $this->getPendingCharges($idBooking);
            if (!empty($pendingCharges)) {
                return [
                    'success' => false,
                    'message' => 'Existen cargos pendientes de pago',
                    'data'    => ['pending_charges' => $pendingCharges],
                ];
            }

            // Actualizar estado
            $booking->booking_status = self::BOOKING_STATUS['CHECKED_OUT'];
            $booking->checkout_date  = date('Y-m-d H:i:s');
            $booking->update();

            // Liberar habitación
            $this->releaseRoom($booking->id_room);

            // Registrar en log
            $this->logCheckOut($idBooking, $idAgent);

            // Disparar hook (notificará a housekeeping)
            Hook::exec('actionBookingStatusChange', [
                'booking'    => $booking,
                'new_status' => self::BOOKING_STATUS['CHECKED_OUT'],
            ]);

            return [
                'success' => true,
                'message' => 'Check-out realizado exitosamente',
                'data'    => ['checkout_at' => date('Y-m-d H:i:s')],
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error interno al procesar check-out'];
        }
    }

    // ──────────────────────────────────────────────────────────
    //  MÉTODOS PRIVADOS
    // ──────────────────────────────────────────────────────────

    private function countArrivalsToday(string $date): int
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'htl_booking_detail`
             WHERE DATE(booking_date_from) = "' . pSQL($date) . '"
             AND booking_status = ' . self::BOOKING_STATUS['CONFIRMED']
        );
    }

    private function countDeparturesToday(string $date): int
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'htl_booking_detail`
             WHERE DATE(booking_date_to) = "' . pSQL($date) . '"
             AND booking_status = ' . self::BOOKING_STATUS['CHECKED_IN']
        );
    }

    private function countInHouseGuests(): int
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'htl_booking_detail`
             WHERE booking_status = ' . self::BOOKING_STATUS['CHECKED_IN']
        );
    }

    private function countAvailableRooms(string $date): int
    {
        // Implementar lógica de disponibilidad
        return 0;
    }

    private function isRoomAvailable(int $idRoom): bool
    {
        // Verificar que la habitación no esté ocupada ni en limpieza bloqueante
        return true; // TODO: implementar
    }

    private function assignRoom(int $idBooking, int $idRoom): bool
    {
        return (bool) Db::getInstance()->insert('smart_room_assignment', [
            'id_booking'  => (int) $idBooking,
            'id_room'     => (int) $idRoom,
            'assigned_at' => date('Y-m-d H:i:s'),
            'active'      => 1,
        ]);
    }

    private function releaseRoom(int $idRoom): bool
    {
        return Db::getInstance()->update('smart_room_assignment',
            ['active' => 0, 'released_at' => date('Y-m-d H:i:s')],
            'id_room = ' . (int) $idRoom . ' AND active = 1'
        );
    }

    private function saveGuestDocument(int $idBooking, array $data): bool
    {
        return (bool) Db::getInstance()->insert('smart_guest_document', [
            'id_booking'   => (int) $idBooking,
            'id_type'      => pSQL($data['id_type'] ?? 'OTHER'),
            'id_number'    => pSQL($data['id_number'] ?? ''),
            'id_country'   => pSQL($data['id_country'] ?? ''),
            'scanned_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    private function getPendingCharges(int $idBooking): array
    {
        // TODO: integrar con módulo de cargos
        return [];
    }

    private function getRoomNumber(int $idRoom): string
    {
        return (string) Db::getInstance()->getValue(
            'SELECT room_num FROM `' . _DB_PREFIX_ . 'htl_room_info`
             WHERE id_room = ' . (int) $idRoom
        );
    }

    private function logCheckIn(int $idBooking, int $idRoom, int $idAgent): void
    {
        Db::getInstance()->insert('smart_checkin_log', [
            'id_booking'  => (int) $idBooking,
            'id_room'     => (int) $idRoom,
            'id_agent'    => (int) $idAgent,
            'checkin_at'  => date('Y-m-d H:i:s'),
            'ip_address'  => pSQL($_SERVER['REMOTE_ADDR'] ?? ''),
        ]);
    }

    private function logCheckOut(int $idBooking, int $idAgent): void
    {
        Db::getInstance()->insert('smart_checkout_log', [
            'id_booking'  => (int) $idBooking,
            'id_agent'    => (int) $idAgent,
            'checkout_at' => date('Y-m-d H:i:s'),
            'ip_address'  => pSQL($_SERVER['REMOTE_ADDR'] ?? ''),
        ]);
    }

    private function sendWelcomeMessage(object $booking): void
    {
        // TODO: integrar con módulo smart_whatsapp
        Hook::exec('actionSmartSendWhatsApp', [
            'template'   => 'welcome_checkin',
            'id_booking' => $booking->id,
        ]);
    }

    // ──────────────────────────────────────────────────────────
    //  CONFIGURACIÓN
    // ──────────────────────────────────────────────────────────

    public function getContent(): string
    {
        $output = '';

        if (Tools::isSubmit('submit_smart_frontdesk')) {
            $output .= $this->saveConfiguration();
        }

        return $output . $this->renderConfigForm();
    }

    private function saveConfiguration(): string
    {
        $fields = [
            'SMART_FD_CHECKIN_TIME'       => Tools::getValue('SMART_FD_CHECKIN_TIME'),
            'SMART_FD_CHECKOUT_TIME'      => Tools::getValue('SMART_FD_CHECKOUT_TIME'),
            'SMART_FD_EARLY_CHECKIN_FEE'  => (float) Tools::getValue('SMART_FD_EARLY_CHECKIN_FEE'),
            'SMART_FD_LATE_CHECKOUT_FEE'  => (float) Tools::getValue('SMART_FD_LATE_CHECKOUT_FEE'),
            'SMART_FD_WHATSAPP_ENABLED'   => (int) Tools::getValue('SMART_FD_WHATSAPP_ENABLED'),
            'SMART_FD_KIOSK_ENABLED'      => (int) Tools::getValue('SMART_FD_KIOSK_ENABLED'),
            'SMART_FD_AUTO_ASSIGN_ROOMS'  => (int) Tools::getValue('SMART_FD_AUTO_ASSIGN_ROOMS'),
            'SMART_FD_PRE_CHECKIN_HOURS'  => (int) Tools::getValue('SMART_FD_PRE_CHECKIN_HOURS'),
        ];

        foreach ($fields as $key => $value) {
            Configuration::updateValue($key, $value);
        }

        return $this->displayConfirmation($this->l('Configuración guardada correctamente.'));
    }

    private function renderConfigForm(): string
    {
        $fieldsForm = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Configuración de Front Desk'),
                    'icon'  => 'icon-hotel',
                ],
                'input' => [
                    [
                        'type'  => 'text',
                        'label' => $this->l('Hora de Check-in'),
                        'name'  => 'SMART_FD_CHECKIN_TIME',
                        'desc'  => $this->l('Formato HH:MM (ej: 15:00)'),
                        'required' => true,
                    ],
                    [
                        'type'  => 'text',
                        'label' => $this->l('Hora de Check-out'),
                        'name'  => 'SMART_FD_CHECKOUT_TIME',
                        'desc'  => $this->l('Formato HH:MM (ej: 12:00)'),
                        'required' => true,
                    ],
                    [
                        'type'  => 'switch',
                        'label' => $this->l('Activar integración WhatsApp'),
                        'name'  => 'SMART_FD_WHATSAPP_ENABLED',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'on', 'value' => 1, 'label' => $this->l('Sí')],
                            ['id' => 'off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                    [
                        'type'  => 'switch',
                        'label' => $this->l('Activar Kiosko de Auto Check-in'),
                        'name'  => 'SMART_FD_KIOSK_ENABLED',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'on', 'value' => 1, 'label' => $this->l('Sí')],
                            ['id' => 'off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Guardar'),
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->module        = $this;
        $helper->name_controller = $this->name;
        $helper->token         = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex  = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->submit_action = 'submit_smart_frontdesk';

        foreach ($fieldsForm['form']['input'] as $field) {
            $helper->fields_value[$field['name']] = Configuration::get($field['name']);
        }

        return $helper->generateForm([$fieldsForm]);
    }
}
