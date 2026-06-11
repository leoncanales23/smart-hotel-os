<?php
/**
 * SmartHotelOS — AdminSmartHkBoard Controller
 * Grupo Smart de Administración
 *
 * Panel visual de pisos en tiempo real para supervisoras de housekeeping.
 */
if (!defined('_PS_VERSION_')) { exit; }

class AdminSmartHkBoardController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->bootstrap = true;
    }

    public function initContent(): void
    {
        $this->initToolbar();
        $hk         = Module::getInstanceByName('smart_housekeeping');
        $idProperty = (int) Configuration::get('DEFAULT_PROPERTY_ID') ?: 1;
        $today      = date('Y-m-d');

        /* Generar plan del día si no existe aún */
        if (Tools::isSubmit('generate_plan') && $hk) {
            $result = $hk->generateDailyPlan($today, $idProperty);
            if ($result['success']) {
                $this->confirmations[] = sprintf(
                    $this->l('%d tareas asignadas. %d habitaciones sin personal suficiente.'),
                    $result['tasks_created'],
                    $result['rooms_pending']
                );
            }
        }

        /* Datos para la vista */
        $roomStatuses = Db::getInstance()->executeS(
            'SELECT r.id_room, r.room_num, r.id_floor,
                    COALESCE(rs.status, "VD") as hk_status,
                    rs.updated_at as status_since,
                    t.id as id_task, t.task_type, t.priority, t.status as task_status,
                    CONCAT(s.firstname, " ", s.lastname) as staff_name,
                    t.started_at
             FROM `' . _DB_PREFIX_ . 'htl_room_info` r
             LEFT JOIN `' . _DB_PREFIX_ . 'smart_hk_room_status` rs ON rs.id_room = r.id_room
             LEFT JOIN `' . _DB_PREFIX_ . 'smart_hk_task` t
               ON t.id_room = r.id_room AND t.status IN ("PENDING","ASSIGNED","IN_PROGRESS")
               AND DATE(t.scheduled_for) = "' . $today . '"
             LEFT JOIN `' . _DB_PREFIX_ . 'smart_hk_staff` s ON s.id_staff = t.id_staff
             WHERE r.id_hotel = ' . $idProperty . ' AND r.is_active = 1
             ORDER BY r.id_floor ASC, r.room_num ASC'
        ) ?: [];

        /* Agrupar por piso */
        $byFloor = [];
        foreach ($roomStatuses as $room) {
            $byFloor[(int) $room['id_floor']][] = $room;
        }
        ksort($byFloor);

        /* Stats */
        $statusCounts = array_count_values(array_column($roomStatuses, 'hk_status'));

        /* Staff del día */
        $staff = Db::getInstance()->executeS(
            'SELECT s.*,
                    COUNT(t.id) as tasks_assigned,
                    SUM(t.status = "COMPLETED") as tasks_done
             FROM `' . _DB_PREFIX_ . 'smart_hk_staff` s
             LEFT JOIN `' . _DB_PREFIX_ . 'smart_hk_task` t
               ON t.id_staff = s.id_staff AND DATE(t.scheduled_for) = "' . $today . '"
             WHERE s.id_property = ' . $idProperty . ' AND s.active = 1
             GROUP BY s.id_staff
             ORDER BY s.role DESC, s.firstname ASC'
        ) ?: [];

        $this->context->smarty->assign([
            'smart_rooms_by_floor' => $byFloor,
            'smart_status_counts'  => $statusCounts,
            'smart_staff'          => $staff,
            'smart_today'          => $today,
            'smart_token'          => Tools::getAdminTokenLite('AdminSmartHkBoard'),
            'smart_api_key'        => Configuration::get('SMART_API_MASTER_KEY') ?: '',
            'smart_property_id'    => $idProperty,
            'smart_status_labels'  => [
                'CL' => ['Limpia',            '#22c55e'],
                'IN' => ['Inspeccionada',      '#10b981'],
                'VC' => ['Vacante Limpia',     '#86efac'],
                'VD' => ['Vacante Sucia',      '#f59e0b'],
                'OD' => ['Ocupada Sucia',      '#ef4444'],
                'OC' => ['Ocupada Limpia',     '#3b82f6'],
                'IP' => ['En Limpieza',        '#8b5cf6'],
                'DND'=> ['No Molestar',        '#6b7280'],
                'OO' => ['Fuera de Servicio',  '#dc2626'],
                'OS' => ['Fuera Temporal',     '#d97706'],
            ],
        ]);

        $this->content = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'smart_housekeeping/views/templates/admin/board.tpl'
        );
        parent::initContent();
    }

    public function initToolbar(): void
    {
        parent::initToolbar();
        $this->toolbar_btn['generate'] = [
            'href'  => self::$currentIndex . '&token=' . $this->token . '&generate_plan=1',
            'desc'  => $this->l('Generar plan del día'),
            'icon'  => 'process-icon-new',
        ];
    }

    /** AJAX: actualizar estado de una habitación */
    public function ajaxProcessUpdateRoomStatus(): void
    {
        $idRoom    = (int) Tools::getValue('id_room');
        $newStatus = Tools::getValue('status');
        $hk        = Module::getInstanceByName('smart_housekeeping');

        if ($hk && $idRoom && $newStatus) {
            $ok = $hk->updateRoomStatus($idRoom, $newStatus);
            $this->ajaxDie(json_encode(['success' => $ok]));
        }
        $this->ajaxDie(json_encode(['success' => false, 'error' => 'Datos inválidos']));
    }

    /** AJAX: completar tarea de housekeeping */
    public function ajaxProcessCompleteTask(): void
    {
        $idTask  = (int) Tools::getValue('id_task');
        $idStaff = (int) Tools::getValue('id_staff');
        $hk      = Module::getInstanceByName('smart_housekeeping');

        if ($hk && $idTask) {
            $result = $hk->completeCleaning($idTask, $idStaff ?: (int) $this->context->employee->id, [
                'notes' => Tools::getValue('notes', ''),
            ]);
            $this->ajaxDie(json_encode($result));
        }
        $this->ajaxDie(json_encode(['success' => false]));
    }
}
