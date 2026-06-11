<?php
/**
 * SmartHotelOS — AdminSmartDashboard Controller
 * Grupo Smart de Administración
 */
if (!defined('_PS_VERSION_')) { exit; }

class AdminSmartDashboardController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->bootstrap = true;
    }

    public function initContent(): void
    {
        $idProperty = (int) (Configuration::get('DEFAULT_PROPERTY_ID') ?: 1);
        $dash       = Module::getInstanceByName('smart_dashboard');

        /* Snapshot del día anterior si aún no existe */
        if ($dash && Tools::isSubmit('snapshot')) {
            $ok = $dash->saveSnapshot($idProperty, date('Y-m-d', strtotime('-1 day')));
            if ($ok) {
                $this->confirmations[] = $this->l('Snapshot guardado correctamente.');
            }
        }

        /* Evaluar alertas automáticas */
        if ($dash) {
            $dash->evaluateAlerts($idProperty);
        }

        $this->context->smarty->assign([
            'smart_api_key'     => Configuration::get('SMART_API_MASTER_KEY') ?: '',
            'smart_property_id' => $idProperty,
            'smart_token'       => Tools::getAdminTokenLite('AdminSmartDashboard'),
        ]);

        $this->content = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'smart_dashboard/views/templates/admin/dashboard.tpl'
        );
        parent::initContent();
    }

    public function initToolbar(): void
    {
        parent::initToolbar();
        $this->toolbar_btn['snapshot'] = [
            'href' => self::$currentIndex . '&token=' . $this->token . '&snapshot=1',
            'desc' => $this->l('Guardar snapshot de ayer'),
            'icon' => 'process-icon-save',
        ];
    }
}
