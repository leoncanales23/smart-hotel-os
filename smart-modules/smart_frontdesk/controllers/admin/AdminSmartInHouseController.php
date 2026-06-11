<?php
/**
 * SmartHotelOS — AdminSmartInHouse Controller
 * Grupo Smart de Administración — Huéspedes en Casa
 */
if (!defined('_PS_VERSION_')) { exit; }

class AdminSmartInHouseController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->bootstrap = true;
    }

    public function initContent(): void
    {
        $this->initToolbar();
        $today     = date('Y-m-d');
        $idProperty = (int) (Configuration::get('DEFAULT_PROPERTY_ID') ?: 1);

        // Huéspedes actualmente en casa con info de habitación y perfil
        $inHouse = Db::getInstance()->executeS(
            'SELECT b.*,
                    c.firstname, c.lastname, c.email, c.phone_mobile,
                    gp.guest_code, gp.vip_level, gp.segment, gp.total_stays,
                    gp.internal_notes as guest_notes,
                    ra.room_num as room_number,
                    rt.name as room_type_name,
                    DATEDIFF(b.booking_date_to, "' . $today . '") as nights_remaining,
                    DATEDIFF("' . $today . '", b.booking_date_from) as nights_stayed,
                    hks.status as room_hk_status,
                    (SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'smart_maint_ticket` mt
                     WHERE mt.id_room = b.id_room AND mt.status NOT IN ("closed","cancelled")) as open_tickets,
                    (SELECT SUM(rc2.amount) FROM `' . _DB_PREFIX_ . 'smart_room_charge` rc2
                     WHERE rc2.id_booking = b.id AND rc2.voided = 0) as total_charges
             FROM `' . _DB_PREFIX_ . 'htl_booking_detail` b
             JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer = b.id_customer
             LEFT JOIN `' . _DB_PREFIX_ . 'smart_guest_profile` gp ON gp.id_customer = b.id_customer
             LEFT JOIN `' . _DB_PREFIX_ . 'htl_room_info` ra ON ra.id_room = b.id_room
             LEFT JOIN `' . _DB_PREFIX_ . 'htl_room_type` rt ON rt.id_room_type = b.id_room_type
             LEFT JOIN `' . _DB_PREFIX_ . 'smart_hk_room_status` hks ON hks.id_room = b.id_room
             WHERE b.booking_status IN (2, 6)
             AND DATE(b.booking_date_from) <= "' . $today . '"
             AND DATE(b.booking_date_to) > "' . $today . '"
             ORDER BY gp.vip_level DESC, b.booking_date_to ASC'
        ) ?: [];

        // Calcular estadísticas
        $totalGuests    = count($inHouse);
        $vipGuests      = count(array_filter($inHouse, fn($g) => (int)$g['vip_level'] > 0));
        $checkoutToday  = count(array_filter($inHouse, fn($g) => $g['nights_remaining'] == 0));
        $withIssues     = count(array_filter($inHouse, fn($g) => (int)$g['open_tickets'] > 0));

        // Ocasiones especiales hoy
        $specialToday = Db::getInstance()->executeS(
            'SELECT oc.*, gp.guest_code, c.firstname, c.lastname, ra.room_num as room_number
             FROM `' . _DB_PREFIX_ . 'smart_guest_occasion` oc
             JOIN `' . _DB_PREFIX_ . 'smart_guest_profile` gp ON gp.id = oc.id_guest
             JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer = gp.id_customer
             JOIN `' . _DB_PREFIX_ . 'htl_booking_detail` b ON b.id_customer = gp.id_customer
               AND b.booking_status IN (2,6)
             JOIN `' . _DB_PREFIX_ . 'htl_room_info` ra ON ra.id_room = b.id_room
             WHERE oc.active = 1
             AND MONTH(oc.occasion_date) = MONTH(CURDATE())
             AND DAY(oc.occasion_date) = DAY(CURDATE())'
        ) ?: [];

        $this->context->smarty->assign([
            'smart_in_house'     => $inHouse,
            'smart_total'        => $totalGuests,
            'smart_vip'          => $vipGuests,
            'smart_checkout_today' => $checkoutToday,
            'smart_with_issues'  => $withIssues,
            'smart_occasions'    => $specialToday,
            'smart_today'        => $today,
            'smart_token'        => Tools::getAdminTokenLite('AdminSmartInHouse'),
        ]);

        $this->content = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'smart_frontdesk/views/templates/admin/inhouse.tpl'
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
    }
}
