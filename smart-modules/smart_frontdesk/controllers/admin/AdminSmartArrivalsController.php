<?php
/**
 * SmartHotelOS — AdminSmartArrivals Controller
 * Grupo Smart de Administración
 *
 * Panel de llegadas del día: lista de reservas con check-in pendiente,
 * botones de acción, filtros y estado en tiempo real.
 */

if (!defined('_PS_VERSION_')) { exit; }

class AdminSmartArrivalsController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->bootstrap  = true;
        $this->table      = 'htl_booking_detail';
        $this->identifier = 'id';
        $this->lang       = false;
        $this->allow_export = true;
        $this->_defaultOrderBy  = 'booking_date_from';
        $this->_defaultOrderWay = 'ASC';
    }

    public function init(): void
    {
        parent::init();
        // Asegurar que el módulo esté activo
        if (!Module::isInstalled('smart_frontdesk')) {
            $this->errors[] = $this->l('El módulo Smart Front Desk no está instalado.');
        }
    }

    /**
     * Define las columnas de la tabla de llegadas.
     */
    public function initContent(): void
    {
        $this->initToolbar();
        $this->initPageHeaderToolbar();

        // KPIs del día para el header
        $frontDesk = Module::getInstanceByName('smart_frontdesk');
        $today     = date('Y-m-d');

        $this->context->smarty->assign([
            'smart_page_title'      => $this->l('Llegadas del Día') . ' — ' . date('d/m/Y'),
            'smart_arrivals_count'  => $frontDesk ? Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'htl_booking_detail`
                 WHERE DATE(booking_date_from) = "' . $today . '"
                 AND booking_status IN (1,2)'
            ) : 0,
            'smart_checkedin_count' => $frontDesk ? Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'htl_booking_detail`
                 WHERE DATE(booking_date_from) = "' . $today . '"
                 AND booking_status = 2'
            ) : 0,
        ]);

        // Cargar llegadas del día con datos de perfil de huésped
        $arrivals = $this->getArrivalsWithProfiles($today);
        $this->context->smarty->assign([
            'smart_arrivals'     => $arrivals,
            'smart_today'        => $today,
            'smart_module_token' => Tools::getAdminTokenLite('AdminSmartArrivals'),
        ]);

        $this->content = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'smart_frontdesk/views/templates/admin/arrivals.tpl'
        );

        parent::initContent();
    }

    public function initToolbar(): void
    {
        parent::initToolbar();
        $this->toolbar_btn['refresh'] = [
            'href' => self::$currentIndex . '&token=' . $this->token,
            'desc' => $this->l('Actualizar'),
            'icon' => 'process-icon-refresh',
        ];
        $this->toolbar_btn['print'] = [
            'href' => self::$currentIndex . '&token=' . $this->token . '&action=printArrivalsList',
            'desc' => $this->l('Imprimir lista'),
            'icon' => 'process-icon-print',
        ];
    }

    /**
     * Procesar acción de check-in desde el panel.
     */
    public function processCheckIn(): void
    {
        $idBooking = (int) Tools::getValue('id_booking');
        $idRoom    = (int) Tools::getValue('id_room');

        if (!$idBooking || !$idRoom) {
            $this->errors[] = $this->l('Datos incompletos para el check-in.');
            return;
        }

        $frontDesk = Module::getInstanceByName('smart_frontdesk');
        if (!$frontDesk) {
            $this->errors[] = $this->l('Módulo Front Desk no disponible.');
            return;
        }

        $guestData = [
            'id_type'    => Tools::getValue('id_type', 'RUT'),
            'id_number'  => Tools::getValue('id_number', ''),
            'id_country' => Tools::getValue('id_country', 'CL'),
        ];

        $result = $frontDesk->processCheckIn(
            $idBooking,
            $idRoom,
            $guestData,
            (int) $this->context->employee->id
        );

        if ($result['success']) {
            $this->confirmations[] = $this->l('Check-in realizado. Habitación: ') . $result['data']['room_number'];
        } else {
            $this->errors[] = $result['message'];
        }
    }

    /**
     * Obtener llegadas con perfiles de huésped enriquecidos.
     */
    private function getArrivalsWithProfiles(string $date): array
    {
        $rows = Db::getInstance()->executeS(
            'SELECT b.*,
                    c.firstname, c.lastname, c.email, c.phone,
                    gp.guest_code, gp.vip_level, gp.segment, gp.total_stays,
                    gp.internal_notes as guest_notes,
                    rt.name as room_type_name,
                    ra.room_num as assigned_room
             FROM `' . _DB_PREFIX_ . 'htl_booking_detail` b
             JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer = b.id_customer
             LEFT JOIN `' . _DB_PREFIX_ . 'smart_guest_profile` gp ON gp.id_customer = b.id_customer
             LEFT JOIN `' . _DB_PREFIX_ . 'htl_room_type` rt ON rt.id_room_type = b.id_room_type
             LEFT JOIN `' . _DB_PREFIX_ . 'smart_room_assignment` ra
               ON ra.id_booking = b.id AND ra.active = 1
             WHERE DATE(b.booking_date_from) = "' . pSQL($date) . '"
             AND b.booking_status IN (1, 2)
             ORDER BY b.booking_date_from ASC, gp.vip_level DESC'
        ) ?: [];

        // Enriquecer con alergias y ocasiones especiales
        foreach ($rows as &$row) {
            if (!empty($row['guest_code'])) {
                $idGuest = Db::getInstance()->getValue(
                    'SELECT id FROM `' . _DB_PREFIX_ . 'smart_guest_profile`
                     WHERE guest_code = "' . pSQL($row['guest_code']) . '"'
                );
                if ($idGuest) {
                    $row['allergies'] = Db::getInstance()->executeS(
                        'SELECT allergy_type, severity FROM `' . _DB_PREFIX_ . 'smart_guest_allergy`
                         WHERE id_guest = ' . (int) $idGuest
                    ) ?: [];
                    $row['occasions'] = Db::getInstance()->executeS(
                        'SELECT occasion_type, occasion_date FROM `' . _DB_PREFIX_ . 'smart_guest_occasion`
                         WHERE id_guest = ' . (int) $idGuest . ' AND active = 1
                         AND DAYOFYEAR(occasion_date) BETWEEN DAYOFYEAR(CURDATE()) AND DAYOFYEAR(DATE_ADD(CURDATE(), INTERVAL 3 DAY))'
                    ) ?: [];
                }
            }
        }
        unset($row);

        return $rows;
    }

    public function postProcess(): void
    {
        $action = Tools::getValue('action');
        if ($action === 'checkIn') {
            $this->processCheckIn();
        }
        parent::postProcess();
    }
}
