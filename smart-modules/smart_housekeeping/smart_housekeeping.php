<?php
/**
 * SmartHotelOS — Módulo Smart Housekeeping
 * Grupo Smart de Administración
 *
 * Gestión completa de pisos y habitaciones: asignación de camareras,
 * control de estado de habitaciones, inspecciones, objetos perdidos
 * y comunicación en tiempo real. Estándar Forbes 5 estrellas.
 *
 * @module      smart_housekeeping
 * @version     1.0.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Smart_Housekeeping extends Module
{
    const MODULE_VERSION = '1.0.0';

    /** Estados de habitación para housekeeping */
    const ROOM_STATUS = [
        'CLEAN'           => 'CL',  // Limpia y lista
        'DIRTY'           => 'DI',  // Sucia (check-out reciente o en uso)
        'IN_PROGRESS'     => 'IP',  // En limpieza ahora
        'INSPECTED'       => 'IN',  // Limpia e inspeccionada (supervisora)
        'OUT_OF_ORDER'    => 'OO',  // Fuera de servicio (mantenimiento)
        'OUT_OF_SERVICE'  => 'OS',  // Fuera de servicio temporal
        'PICK_UP'         => 'PU',  // Requiere retoque rápido
        'VACANT_CLEAN'    => 'VC',  // Vacante y limpia (no inspeccionada)
        'VACANT_DIRTY'    => 'VD',  // Vacante y sucia
        'OCCUPIED_CLEAN'  => 'OC',  // Ocupada y limpia
        'OCCUPIED_DIRTY'  => 'OD',  // Ocupada y sucia
        'DO_NOT_DISTURB'  => 'DND', // No molestar
    ];

    /** Tipos de tarea de housekeeping */
    const TASK_TYPES = [
        'CHECKOUT_CLEAN'  => 'Limpieza post check-out',
        'STAYOVER_CLEAN'  => 'Limpieza en estancia',
        'TURNDOWN'        => 'Servicio de cobertura (turndown)',
        'INSPECTION'      => 'Inspección de supervisora',
        'DEEP_CLEAN'      => 'Limpieza profunda',
        'SPECIAL_REQUEST' => 'Solicitud especial del huésped',
        'LOST_FOUND'      => 'Objeto encontrado',
        'MAINTENANCE_REQ' => 'Solicitud de mantenimiento',
    ];

    /** Prioridades */
    const PRIORITY = [
        'LOW'    => 1,
        'NORMAL' => 2,
        'HIGH'   => 3,
        'URGENT' => 4,
        'VIP'    => 5,
    ];

    public function __construct()
    {
        $this->name    = 'smart_housekeeping';
        $this->tab     = 'administration';
        $this->version = self::MODULE_VERSION;
        $this->author  = 'Smart Hotel Group';
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('Smart Housekeeping');
        $this->description = $this->l(
            'Gestión completa de pisos: asignación, estado de habitaciones, ' .
            'inspecciones, objetos perdidos y KPIs de productividad.'
        );
    }

    public function install(): bool
    {
        return parent::install()
            && $this->installSql()
            && $this->installTabs()
            && $this->registerHooks()
            && $this->installDefaultConfig();
    }

    public function uninstall(): bool
    {
        return parent::uninstall() && $this->uninstallSql() && $this->uninstallTabs();
    }

    private function installSql(): bool
    {
        require_once dirname(__FILE__) . '/sql/install.php';
        return true;
    }

    private function installTabs(): bool
    {
        $tabs = [
            ['AdminSmartHousekeeping',    'Housekeeping',          'AdminSmartHotel'],
            ['AdminSmartHkBoard',         'Panel de Pisos',        'AdminSmartHousekeeping'],
            ['AdminSmartHkAssignment',    'Asignación de Tareas',  'AdminSmartHousekeeping'],
            ['AdminSmartHkInspection',    'Inspecciones',          'AdminSmartHousekeeping'],
            ['AdminSmartHkLostFound',     'Objetos Perdidos',      'AdminSmartHousekeeping'],
            ['AdminSmartHkReports',       'Reportes de Pisos',     'AdminSmartHousekeeping'],
        ];

        foreach ($tabs as [$className, $tabName, $parent]) {
            $tab = new Tab();
            $tab->active = 1;
            $tab->class_name = $className;
            $tab->module = $this->name;
            $tab->id_parent = (int) Tab::getIdFromClassName($parent);
            foreach (Language::getLanguages() as $lang) {
                $tab->name[$lang['id_lang']] = $tabName;
            }
            $tab->add();
        }

        return true;
    }

    private function registerHooks(): bool
    {
        return $this->registerHook([
            'actionSmartCheckOut',          // Cuando hace checkout → crear tarea de limpieza
            'actionSmartRoomNeedsHousekeeping',
            'displaySmartDashboardWidgets',
            'displaySmartFrontDeskAlerts',
        ]);
    }

    private function installDefaultConfig(): bool
    {
        $configs = [
            'SMART_HK_AUTO_CREATE_TASKS'    => 1,
            'SMART_HK_TURNDOWN_ENABLED'     => 1,
            'SMART_HK_TURNDOWN_START_HOUR'  => '17',
            'SMART_HK_TURNDOWN_END_HOUR'    => '21',
            'SMART_HK_INSPECTION_REQUIRED'  => 1,
            'SMART_HK_DEEP_CLEAN_DAYS'      => 14,
            'SMART_HK_NOTIFY_FD_ON_CLEAN'   => 1,
            'SMART_HK_MAX_ROOMS_PER_STAFF'  => 16,
        ];

        foreach ($configs as $key => $value) {
            Configuration::updateValue($key, $value);
        }

        return true;
    }

    private function uninstallSql(): bool
    {
        $tables = [
            'smart_hk_task', 'smart_hk_assignment', 'smart_hk_room_status',
            'smart_hk_inspection', 'smart_hk_lost_found', 'smart_hk_staff',
        ];
        foreach ($tables as $t) {
            Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . pSQL($t) . '`');
        }
        return true;
    }

    private function uninstallTabs(): bool
    {
        foreach (['AdminSmartHousekeeping', 'AdminSmartHkBoard', 'AdminSmartHkAssignment',
                  'AdminSmartHkInspection', 'AdminSmartHkLostFound', 'AdminSmartHkReports'] as $c) {
            $id = (int) Tab::getIdFromClassName($c);
            if ($id) {
                (new Tab($id))->delete();
            }
        }
        return true;
    }

    // ──────────────────────────────────────────────────────────
    //  HOOKS
    // ──────────────────────────────────────────────────────────

    /** Se dispara desde Front Desk al hacer check-out */
    public function hookActionSmartRoomNeedsHousekeeping(array $params): void
    {
        $idRoom    = (int) ($params['id_room'] ?? 0);
        $idBooking = (int) ($params['id_booking'] ?? 0);
        $priority  = $params['priority'] ?? 'NORMAL';

        if (!$idRoom) {
            return;
        }

        // Crear tarea de limpieza post checkout automáticamente
        $this->createTask([
            'id_room'    => $idRoom,
            'id_booking' => $idBooking,
            'task_type'  => 'CHECKOUT_CLEAN',
            'priority'   => self::PRIORITY[$priority] ?? self::PRIORITY['NORMAL'],
            'notes'      => 'Limpieza automática post check-out',
        ]);

        // Marcar habitación como sucia
        $this->updateRoomStatus($idRoom, self::ROOM_STATUS['VACANT_DIRTY']);
    }

    /** Widget para el dashboard */
    public function hookDisplaySmartDashboardWidgets(): string
    {
        $this->context->smarty->assign([
            'hk_pending_tasks'   => $this->countPendingTasks(),
            'hk_in_progress'     => $this->countTasksInProgress(),
            'hk_dirty_rooms'     => $this->countDirtyRooms(),
            'hk_clean_rooms'     => $this->countCleanRooms(),
            'hk_inspected_rooms' => $this->countInspectedRooms(),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/dashboard_widget.tpl');
    }

    // ──────────────────────────────────────────────────────────
    //  LÓGICA DE NEGOCIO
    // ──────────────────────────────────────────────────────────

    /**
     * Crear una nueva tarea de housekeeping.
     */
    public function createTask(array $data): int
    {
        $insert = [
            'id_room'      => (int) ($data['id_room'] ?? 0),
            'id_booking'   => (int) ($data['id_booking'] ?? 0),
            'id_property'  => (int) ($data['id_property'] ?? Configuration::get('DEFAULT_PROPERTY_ID') ?? 1),
            'task_type'    => pSQL($data['task_type'] ?? 'STAYOVER_CLEAN'),
            'priority'     => (int) ($data['priority'] ?? self::PRIORITY['NORMAL']),
            'status'       => 'PENDING',
            'notes'        => pSQL($data['notes'] ?? ''),
            'scheduled_for' => pSQL($data['scheduled_for'] ?? date('Y-m-d')),
            'created_at'   => date('Y-m-d H:i:s'),
        ];

        Db::getInstance()->insert('smart_hk_task', $insert);
        $idTask = (int) Db::getInstance()->Insert_ID();

        // Auto-asignar si hay staff disponible
        if (Configuration::get('SMART_HK_AUTO_CREATE_TASKS') && isset($data['id_staff'])) {
            $this->assignTask($idTask, (int) $data['id_staff']);
        }

        return $idTask;
    }

    /**
     * Asignar tarea a una camarera/o.
     */
    public function assignTask(int $idTask, int $idStaff): bool
    {
        // Verificar carga de trabajo del staff
        $currentLoad = $this->getStaffCurrentLoad($idStaff);
        $maxRooms    = (int) Configuration::get('SMART_HK_MAX_ROOMS_PER_STAFF');

        if ($currentLoad >= $maxRooms) {
            return false; // Staff con carga máxima
        }

        return Db::getInstance()->update('smart_hk_task',
            ['id_staff' => (int) $idStaff, 'status' => 'ASSIGNED', 'assigned_at' => date('Y-m-d H:i:s')],
            'id = ' . (int) $idTask
        );
    }

    /**
     * Iniciar limpieza de una habitación.
     */
    public function startCleaning(int $idTask, int $idStaff): array
    {
        $task = $this->getTask($idTask);
        if (!$task) {
            return ['success' => false, 'message' => 'Tarea no encontrada'];
        }

        // Actualizar tarea
        Db::getInstance()->update('smart_hk_task', [
            'status'       => 'IN_PROGRESS',
            'started_at'   => date('Y-m-d H:i:s'),
            'id_staff'     => (int) $idStaff,
        ], 'id = ' . (int) $idTask);

        // Actualizar estado de habitación
        $this->updateRoomStatus((int) $task['id_room'], self::ROOM_STATUS['IN_PROGRESS']);

        return ['success' => true, 'message' => 'Limpieza iniciada'];
    }

    /**
     * Completar limpieza de una habitación.
     */
    public function completeCleaning(int $idTask, int $idStaff, array $report = []): array
    {
        $task = $this->getTask($idTask);
        if (!$task) {
            return ['success' => false, 'message' => 'Tarea no encontrada'];
        }

        $startedAt   = new DateTime($task['started_at']);
        $completedAt = new DateTime();
        $duration    = $completedAt->diff($startedAt)->i; // minutos

        // Completar tarea
        Db::getInstance()->update('smart_hk_task', [
            'status'          => 'COMPLETED',
            'completed_at'    => $completedAt->format('Y-m-d H:i:s'),
            'duration_minutes' => $duration,
            'completion_notes' => pSQL($report['notes'] ?? ''),
        ], 'id = ' . (int) $idTask);

        // Estado: limpia, pendiente de inspección o directo a VC
        $requireInspection = (bool) Configuration::get('SMART_HK_INSPECTION_REQUIRED');
        $newStatus = $requireInspection
            ? self::ROOM_STATUS['VACANT_CLEAN']
            : self::ROOM_STATUS['INSPECTED'];

        $this->updateRoomStatus((int) $task['id_room'], $newStatus);

        // Notificar a Front Desk si está configurado
        if (Configuration::get('SMART_HK_NOTIFY_FD_ON_CLEAN')) {
            Hook::exec('actionSmartRoomReadyForGuest', [
                'id_room'  => $task['id_room'],
                'id_task'  => $idTask,
                'status'   => $newStatus,
            ]);
        }

        // Reportar objetos encontrados
        if (!empty($report['lost_items'])) {
            foreach ($report['lost_items'] as $item) {
                $this->registerLostAndFound((int) $task['id_room'], $idStaff, $item);
            }
        }

        return [
            'success'  => true,
            'message'  => 'Limpieza completada',
            'duration' => $duration . ' minutos',
        ];
    }

    /**
     * Registrar objeto encontrado en la habitación.
     */
    public function registerLostAndFound(int $idRoom, int $idStaff, array $item): int
    {
        Db::getInstance()->insert('smart_hk_lost_found', [
            'id_room'      => $idRoom,
            'id_staff'     => $idStaff,
            'description'  => pSQL($item['description'] ?? ''),
            'location'     => pSQL($item['location'] ?? ''),
            'category'     => pSQL($item['category'] ?? 'OTHER'),
            'found_at'     => date('Y-m-d H:i:s'),
            'storage_loc'  => pSQL($item['storage_loc'] ?? ''),
            'status'       => 'STORED',
        ]);

        return (int) Db::getInstance()->Insert_ID();
    }

    /**
     * Generar plan de housekeeping para el día.
     * Distribuye las tareas automáticamente entre el staff disponible.
     */
    public function generateDailyPlan(string $date, int $idProperty): array
    {
        $rooms         = $this->getRoomsNeedingCleaning($date, $idProperty);
        $availableStaff = $this->getAvailableStaff($date, $idProperty);

        if (empty($availableStaff)) {
            return ['success' => false, 'message' => 'No hay staff disponible para este día'];
        }

        $maxRooms = (int) Configuration::get('SMART_HK_MAX_ROOMS_PER_STAFF');
        $staffIdx = 0;
        $assigned = 0;

        foreach ($rooms as $room) {
            if ($staffIdx >= count($availableStaff)) {
                break; // Sin más staff disponible
            }

            $idTask = $this->createTask([
                'id_room'      => $room['id_room'],
                'task_type'    => $room['task_type'],
                'priority'     => $room['priority'],
                'scheduled_for' => $date,
                'id_property'  => $idProperty,
            ]);

            $this->assignTask($idTask, (int) $availableStaff[$staffIdx]['id_staff']);
            $assigned++;

            // Rotar staff cuando alcanza su máximo
            if ($this->getStaffCurrentLoad((int) $availableStaff[$staffIdx]['id_staff']) >= $maxRooms) {
                $staffIdx++;
            }
        }

        return [
            'success'         => true,
            'tasks_created'   => $assigned,
            'rooms_pending'   => count($rooms) - $assigned,
            'staff_assigned'  => min($staffIdx + 1, count($availableStaff)),
        ];
    }

    /**
     * Actualizar el estado de una habitación.
     */
    public function updateRoomStatus(int $idRoom, string $status): bool
    {
        $exists = Db::getInstance()->getValue(
            'SELECT id FROM `' . _DB_PREFIX_ . 'smart_hk_room_status`
             WHERE id_room = ' . (int) $idRoom
        );

        if ($exists) {
            return Db::getInstance()->update('smart_hk_room_status',
                ['status' => pSQL($status), 'updated_at' => date('Y-m-d H:i:s')],
                'id_room = ' . (int) $idRoom
            );
        }

        return (bool) Db::getInstance()->insert('smart_hk_room_status', [
            'id_room'    => (int) $idRoom,
            'status'     => pSQL($status),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // ──────────────────────────────────────────────────────────
    //  CONSULTAS
    // ──────────────────────────────────────────────────────────

    private function getTask(int $idTask): ?array
    {
        $result = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'smart_hk_task` WHERE id = ' . (int) $idTask
        );
        return $result ?: null;
    }

    private function countPendingTasks(): int
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'smart_hk_task`
             WHERE status IN ("PENDING","ASSIGNED") AND DATE(scheduled_for) = "' . date('Y-m-d') . '"'
        );
    }

    private function countTasksInProgress(): int
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'smart_hk_task` WHERE status = "IN_PROGRESS"'
        );
    }

    private function countDirtyRooms(): int
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'smart_hk_room_status`
             WHERE status IN ("' . self::ROOM_STATUS['VACANT_DIRTY'] . '","' . self::ROOM_STATUS['OCCUPIED_DIRTY'] . '")'
        );
    }

    private function countCleanRooms(): int
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'smart_hk_room_status`
             WHERE status = "' . self::ROOM_STATUS['VACANT_CLEAN'] . '"'
        );
    }

    private function countInspectedRooms(): int
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'smart_hk_room_status`
             WHERE status = "' . self::ROOM_STATUS['INSPECTED'] . '"'
        );
    }

    private function getStaffCurrentLoad(int $idStaff): int
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'smart_hk_task`
             WHERE id_staff = ' . (int) $idStaff . '
             AND DATE(scheduled_for) = "' . date('Y-m-d') . '"
             AND status IN ("ASSIGNED","IN_PROGRESS")'
        );
    }

    private function getRoomsNeedingCleaning(string $date, int $idProperty): array
    {
        return Db::getInstance()->executeS(
            'SELECT r.id_room,
                    CASE
                        WHEN rs.status = "' . self::ROOM_STATUS['VACANT_DIRTY'] . '" THEN "CHECKOUT_CLEAN"
                        ELSE "STAYOVER_CLEAN"
                    END as task_type,
                    CASE
                        WHEN b.is_vip = 1 THEN ' . self::PRIORITY['VIP'] . '
                        ELSE ' . self::PRIORITY['NORMAL'] . '
                    END as priority
             FROM `' . _DB_PREFIX_ . 'htl_room_info` r
             LEFT JOIN `' . _DB_PREFIX_ . 'smart_hk_room_status` rs ON rs.id_room = r.id_room
             LEFT JOIN `' . _DB_PREFIX_ . 'htl_booking_detail` b ON b.id_room = r.id_room
               AND b.booking_status IN (2,6)
             WHERE rs.status IN (
                "' . self::ROOM_STATUS['VACANT_DIRTY'] . '",
                "' . self::ROOM_STATUS['OCCUPIED_DIRTY'] . '"
             )
             ORDER BY priority DESC'
        ) ?? [];
    }

    private function getAvailableStaff(string $date, int $idProperty): array
    {
        return Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'smart_hk_staff`
             WHERE active = 1 AND id_property = ' . (int) $idProperty
        ) ?? [];
    }

    public function getContent(): string
    {
        return $this->display(__FILE__, 'views/templates/admin/configuration.tpl');
    }
}
