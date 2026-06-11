<?php
/**
 * SmartHotelOS — AdminSmartDepartures Controller
 * Grupo Smart de Administración — Panel de Salidas del Día
 */
if (!defined('_PS_VERSION_')) { exit; }

class AdminSmartDeparturesController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->bootstrap = true;
    }

    public function initContent(): void
    {
        $this->initToolbar();
        $today = date('Y-m-d');

        $departures = Db::getInstance()->executeS(
            'SELECT b.*,
                    c.firstname, c.lastname, c.email, c.phone,
                    gp.guest_code, gp.vip_level, gp.nps_score as guest_nps,
                    rt.name as room_type_name,
                    ra.room_num as room_number,
                    DATEDIFF(b.booking_date_to, b.booking_date_from) as nights,
                    b.total_paid_amount as total,
                    GROUP_CONCAT(rc.amount) as pending_charges_amounts,
                    COUNT(rc.id) as pending_charges_count
             FROM `' . _DB_PREFIX_ . 'htl_booking_detail` b
             JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer = b.id_customer
             LEFT JOIN `' . _DB_PREFIX_ . 'smart_guest_profile` gp ON gp.id_customer = b.id_customer
             LEFT JOIN `' . _DB_PREFIX_ . 'htl_room_type` rt ON rt.id_room_type = b.id_room_type
             LEFT JOIN `' . _DB_PREFIX_ . 'htl_room_info` ra ON ra.id_room = b.id_room
             LEFT JOIN `' . _DB_PREFIX_ . 'smart_room_charge` rc
               ON rc.id_booking = b.id AND rc.voided = 0
             WHERE DATE(b.booking_date_to) = "' . $today . '"
             AND b.booking_status IN (2, 6)
             GROUP BY b.id
             ORDER BY gp.vip_level DESC, b.booking_date_to ASC'
        ) ?: [];

        $stats = [
            'total'        => count($departures),
            'checked_out'  => count(array_filter($departures, fn($d) => (int)$d['booking_status'] === 3)),
            'pending'      => count(array_filter($departures, fn($d) => (int)$d['booking_status'] !== 3)),
            'with_charges' => count(array_filter($departures, fn($d) => (int)$d['pending_charges_count'] > 0)),
        ];

        $this->context->smarty->assign([
            'smart_departures'  => $departures,
            'smart_stats'       => $stats,
            'smart_today'       => $today,
            'smart_token'       => Tools::getAdminTokenLite('AdminSmartDepartures'),
        ]);

        $this->content = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'smart_frontdesk/views/templates/admin/departures.tpl'
        );
        parent::initContent();
    }

    public function processCheckOut(): void
    {
        $idBooking = (int) Tools::getValue('id_booking');
        if (!$idBooking) {
            $this->errors[] = $this->l('ID de reserva no válido.');
            return;
        }

        $frontDesk = Module::getInstanceByName('smart_frontdesk');
        if (!$frontDesk) {
            $this->errors[] = $this->l('Módulo Front Desk no disponible.');
            return;
        }

        $result = $frontDesk->processCheckOut($idBooking, (int) $this->context->employee->id);

        if ($result['success']) {
            $this->confirmations[] = $this->l('Check-out procesado correctamente.');
        } else {
            // Si hay cargos pendientes, mostrarlos
            if (!empty($result['data']['pending_charges'])) {
                $this->errors[] = $this->l('Cargos pendientes: ') .
                    implode(', ', array_column($result['data']['pending_charges'], 'description'));
            } else {
                $this->errors[] = $result['message'];
            }
        }
    }

    public function postProcess(): void
    {
        if (Tools::getValue('action') === 'checkOut') {
            $this->processCheckOut();
        }
        parent::postProcess();
    }

    public function initToolbar(): void
    {
        parent::initToolbar();
        $this->toolbar_btn['refresh'] = [
            'href' => self::$currentIndex . '&token=' . $this->token,
            'desc' => $this->l('Actualizar'),
            'icon' => 'process-icon-refresh',
        ];
    }
}
