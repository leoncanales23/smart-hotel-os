<?php
/**
 * SmartHotelOS — API REST Central
 * Grupo Smart de Administración
 *
 * Punto de entrada único para todas las integraciones externas:
 * channel manager, app móvil, kiosko, WhatsApp bot e IoT.
 * Versionada en /api/smart/v1/
 *
 * @file    smart-api/SmartApiController.php
 * @version 1.0.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class SmartApiController extends ModuleFrontController
{
    /** @var string Versión de la API */
    const API_VERSION = 'v1';

    /** @var int Rate limit: requests por minuto por API key */
    const RATE_LIMIT = 100;

    /** @var array Rutas disponibles: método => endpoint => [módulo, método] */
    const ROUTES = [
        'GET' => [
            '/rooms/availability'         => ['availability', 'getRoomAvailability'],
            '/rooms/types'                => ['rooms', 'getRoomTypes'],
            '/rooms/status'               => ['rooms', 'getRoomStatusBoard'],
            '/reservations'               => ['reservations', 'listReservations'],
            '/reservations/{id}'          => ['reservations', 'getReservation'],
            '/guests/{id}/profile'        => ['guests', 'getGuestProfile'],
            '/guests/{id}/briefing'       => ['guests', 'getArrivalBriefing'],
            '/housekeeping/tasks'         => ['housekeeping', 'getTasks'],
            '/housekeeping/board'         => ['housekeeping', 'getFloorBoard'],
            '/rates/availability'         => ['rates', 'getBestAvailableRate'],
            '/rates/yield/suggestions'    => ['rates', 'getYieldSuggestions'],
            '/dashboard/kpis'             => ['dashboard', 'getDailyKpis'],
            '/dashboard/kpis/period'      => ['dashboard', 'getPeriodKpis'],
            '/dashboard/alerts'           => ['dashboard', 'getAlerts'],
            '/health'                     => ['system', 'healthCheck'],
        ],
        'POST' => [
            '/reservations'               => ['reservations', 'createReservation'],
            '/reservations/{id}/checkin'  => ['reservations', 'checkIn'],
            '/reservations/{id}/checkout' => ['reservations', 'checkOut'],
            '/housekeeping/tasks'         => ['housekeeping', 'createTask'],
            '/housekeeping/tasks/{id}/start'    => ['housekeeping', 'startTask'],
            '/housekeeping/tasks/{id}/complete' => ['housekeeping', 'completeTask'],
            '/guests'                     => ['guests', 'createOrUpdateGuest'],
            '/guests/{id}/preferences'    => ['guests', 'setPreference'],
            '/guests/{id}/tags'           => ['guests', 'addTag'],
            '/dashboard/alerts/{id}/resolve' => ['dashboard', 'resolveAlert'],
            '/rates/yield/apply'          => ['rates', 'applyYieldSuggestion'],
            '/notifications/send'         => ['notifications', 'send'],
        ],
        'PUT' => [
            '/reservations/{id}'          => ['reservations', 'updateReservation'],
            '/housekeeping/tasks/{id}'    => ['housekeeping', 'updateTask'],
            '/rates/{id}'                 => ['rates', 'updateRate'],
        ],
        'DELETE' => [
            '/reservations/{id}'          => ['reservations', 'cancelReservation'],
        ],
    ];

    public function init(): void
    {
        parent::init();
        $this->ajax = true;
        header('Content-Type: application/json; charset=UTF-8');
        header('X-SmartHotelOS-Version: ' . self::API_VERSION);
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }

    public function display(): void
    {
        try {
            // 1. Autenticación
            $apiKey = $this->extractApiKey();
            if (!$this->authenticate($apiKey)) {
                $this->respondError(401, 'API key inválida o no autorizada');
                return;
            }

            // 2. Rate limiting
            if (!$this->checkRateLimit($apiKey)) {
                $this->respondError(429, 'Rate limit excedido. Máximo ' . self::RATE_LIMIT . ' requests/minuto');
                return;
            }

            // 3. Parsear ruta
            $method  = $_SERVER['REQUEST_METHOD'];
            $path    = $this->parsePath();
            $params  = $this->parseRouteParams($method, $path);

            if ($params === null) {
                $this->respondError(404, 'Endpoint no encontrado: ' . $method . ' ' . $path);
                return;
            }

            // 4. Parsear body
            $body = [];
            if (in_array($method, ['POST', 'PUT'])) {
                $raw = file_get_contents('php://input');
                $body = json_decode($raw, true) ?? [];
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->respondError(400, 'JSON inválido en el body de la solicitud');
                    return;
                }
            }

            // 5. Dispatch a handler
            $result = $this->dispatch($params, array_merge($_GET, $body));

            // 6. Log de la llamada
            $this->logApiCall($apiKey, $method, $path, 200);

            $this->respondSuccess($result);

        } catch (SmartApiException $e) {
            $this->logApiCall($apiKey ?? 'unknown', $_SERVER['REQUEST_METHOD'] ?? 'GET', '', $e->getCode());
            $this->respondError($e->getCode(), $e->getMessage());
        } catch (Exception $e) {
            PrestaShopLogger::addLog('SmartAPI Error: ' . $e->getMessage(), 3);
            $this->respondError(500, 'Error interno del servidor');
        }
    }

    // ──────────────────────────────────────────────────────────
    //  HANDLERS POR RECURSO
    // ──────────────────────────────────────────────────────────

    private function dispatch(array $routeParams, array $params): array
    {
        [$resource, $action] = $routeParams;

        switch ($resource) {

            case 'availability':
                return $this->handleAvailability($action, $params);

            case 'rooms':
                return $this->handleRooms($action, $params);

            case 'reservations':
                return $this->handleReservations($action, $params);

            case 'guests':
                return $this->handleGuests($action, $params);

            case 'housekeeping':
                return $this->handleHousekeeping($action, $params);

            case 'rates':
                return $this->handleRates($action, $params);

            case 'dashboard':
                return $this->handleDashboard($action, $params);

            case 'notifications':
                return $this->handleNotifications($action, $params);

            case 'system':
                return $this->handleSystem($action, $params);

            default:
                throw new SmartApiException('Recurso desconocido: ' . $resource, 404);
        }
    }

    private function handleAvailability(string $action, array $p): array
    {
        $this->requireParams($p, ['check_in', 'check_out']);

        /** @var HotelRoomTypeClass $htl */
        $htl = Module::getInstanceByName('hotelreservationsystem');

        // Validar fechas
        $checkIn  = $this->validateDate($p['check_in']);
        $checkOut = $this->validateDate($p['check_out']);

        if (strtotime($checkOut) <= strtotime($checkIn)) {
            throw new SmartApiException('check_out debe ser posterior a check_in', 400);
        }

        $idProperty = (int) ($p['id_property'] ?? 1);
        $adults     = (int) ($p['adults'] ?? 2);
        $children   = (int) ($p['children'] ?? 0);

        // Obtener disponibilidad de QloApps
        $availability = HotelRoomTypeAvailData::getAvailableRoomTypes(
            $idProperty, $checkIn, $checkOut, $adults + $children
        ) ?: [];

        // Enriquecer con tarifas Smart
        $rateManager = Module::getInstanceByName('smart_ratemanager');
        $result      = [];

        foreach ($availability as $roomType) {
            $rate = $rateManager
                ? $rateManager->getBestAvailableRate(
                    (int) $roomType['id_room_type'],
                    $checkIn, $checkOut,
                    $p['channel'] ?? 'DIRECT_WEB',
                    $adults, $children
                )
                : null;

            $result[] = [
                'id_room_type'  => $roomType['id_room_type'],
                'name'          => $roomType['name'],
                'available'     => (int) $roomType['available'],
                'max_occupancy' => (int) $roomType['max_persons'],
                'images'        => $roomType['images'] ?? [],
                'amenities'     => $roomType['amenities'] ?? [],
                'rate'          => $rate,
            ];
        }

        return [
            'check_in'    => $checkIn,
            'check_out'   => $checkOut,
            'nights'      => (int) ((strtotime($checkOut) - strtotime($checkIn)) / 86400),
            'adults'      => $adults,
            'children'    => $children,
            'results'     => $result,
            'total'       => count($result),
        ];
    }

    private function handleReservations(string $action, array $p): array
    {
        switch ($action) {
            case 'listReservations':
                return $this->listReservations($p);
            case 'getReservation':
                return $this->getReservation((int) ($p['id'] ?? 0));
            case 'createReservation':
                return $this->createReservation($p);
            case 'checkIn':
                return $this->processCheckIn((int) ($p['id'] ?? 0), $p);
            case 'checkOut':
                return $this->processCheckOut((int) ($p['id'] ?? 0), $p);
            case 'cancelReservation':
                return $this->cancelReservation((int) ($p['id'] ?? 0), $p);
            default:
                throw new SmartApiException('Acción no encontrada: ' . $action, 404);
        }
    }

    private function handleGuests(string $action, array $p): array
    {
        $guestProfile = Module::getInstanceByName('smart_guestprofile');
        if (!$guestProfile) {
            throw new SmartApiException('Módulo smart_guestprofile no disponible', 503);
        }

        switch ($action) {
            case 'getGuestProfile':
                $idCustomer = (int) ($p['id'] ?? 0);
                $profile = $guestProfile->getOrCreateProfile($idCustomer);
                if (empty($profile)) {
                    throw new SmartApiException('Huésped no encontrado', 404);
                }
                return [
                    'profile'      => $profile,
                    'preferences'  => $guestProfile->getPreferences((int) $profile['id']),
                    'allergies'    => $guestProfile->getAllergies((int) $profile['id']),
                    'occasions'    => $guestProfile->getUpcomingOccasions((int) $profile['id']),
                    'tags'         => $guestProfile->getTags((int) $profile['id']),
                ];

            case 'getArrivalBriefing':
                $idCustomer = (int) ($p['id'] ?? 0);
                return $guestProfile->getArrivalBriefing($idCustomer);

            case 'setPreference':
                $this->requireParams($p, ['category', 'key', 'value']);
                return ['success' => $guestProfile->setPreference(
                    (int) $p['id'], $p['category'], $p['key'], $p['value'],
                    $p['source'] ?? 'api'
                )];

            default:
                throw new SmartApiException('Acción no encontrada: ' . $action, 404);
        }
    }

    private function handleHousekeeping(string $action, array $p): array
    {
        $hk = Module::getInstanceByName('smart_housekeeping');
        if (!$hk) {
            throw new SmartApiException('Módulo smart_housekeeping no disponible', 503);
        }

        switch ($action) {
            case 'getFloorBoard':
                return $hk->getFloorBoard((int) ($p['id_property'] ?? 1));

            case 'startTask':
                return $hk->startCleaning((int) $p['id'], (int) ($p['id_staff'] ?? 0));

            case 'completeTask':
                return $hk->completeCleaning((int) $p['id'], (int) ($p['id_staff'] ?? 0), $p);

            default:
                throw new SmartApiException('Acción no encontrada: ' . $action, 404);
        }
    }

    private function handleDashboard(string $action, array $p): array
    {
        $dash = Module::getInstanceByName('smart_dashboard');
        if (!$dash) {
            throw new SmartApiException('Módulo smart_dashboard no disponible', 503);
        }

        $idProperty = (int) ($p['id_property'] ?? 1);

        switch ($action) {
            case 'getDailyKpis':
                return $dash->getDailyKpis($idProperty, $p['date'] ?? '');

            case 'getPeriodKpis':
                $this->requireParams($p, ['from', 'to']);
                return $dash->getPeriodKpis($idProperty, $p['from'], $p['to']);

            case 'getAlerts':
                return ['alerts' => $dash->getActiveAlerts($idProperty)];

            case 'resolveAlert':
                return ['success' => $dash->resolveAlert((int) $p['id'], (int) ($p['id_agent'] ?? 0))];

            default:
                throw new SmartApiException('Acción no encontrada: ' . $action, 404);
        }
    }

    private function handleSystem(string $action, array $p): array
    {
        if ($action === 'healthCheck') {
            // Verificar conectividad con los subsistemas
            $dbOk    = (bool) Db::getInstance()->getValue('SELECT 1');
            $redisOk = false;
            try {
                $redis = new Redis();
                $redis->connect(
                    Configuration::get('REDIS_HOST') ?: '127.0.0.1',
                    (int) (Configuration::get('REDIS_PORT') ?: 6379)
                );
                $redisOk = $redis->ping() === '+PONG';
            } catch (Exception $e) { /* no crítico */ }

            return [
                'status'    => $dbOk ? 'healthy' : 'degraded',
                'version'   => self::API_VERSION,
                'timestamp' => date('Y-m-d\TH:i:sP'),
                'subsystems' => [
                    'database' => $dbOk  ? 'ok' : 'error',
                    'redis'    => $redisOk ? 'ok' : 'unavailable',
                    'modules'  => $this->checkModules(),
                ],
            ];
        }
        throw new SmartApiException('Acción no encontrada', 404);
    }

    // ──────────────────────────────────────────────────────────
    //  HELPERS
    // ──────────────────────────────────────────────────────────

    private function extractApiKey(): string
    {
        // 1. Header Authorization: Bearer TOKEN
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($auth, 'Bearer ')) {
            return trim(substr($auth, 7));
        }
        // 2. Header X-API-Key
        if (!empty($_SERVER['HTTP_X_API_KEY'])) {
            return $_SERVER['HTTP_X_API_KEY'];
        }
        // 3. Query param api_key
        return $_GET['api_key'] ?? '';
    }

    private function authenticate(string $apiKey): bool
    {
        if (empty($apiKey)) {
            return false;
        }
        // TODO: tabla smart_api_key con tokens hasheados, permisos y propiedad
        // Por ahora verificar contra config
        $validKey = Configuration::get('SMART_API_MASTER_KEY');
        if ($validKey && hash_equals($validKey, $apiKey)) {
            return true;
        }
        // Verificar en tabla de claves
        return (bool) Db::getInstance()->getValue(
            'SELECT id FROM `' . _DB_PREFIX_ . 'smart_api_key`
             WHERE api_key = "' . pSQL(hash('sha256', $apiKey)) . '"
             AND active = 1
             AND (expires_at IS NULL OR expires_at > NOW())'
        );
    }

    private function checkRateLimit(string $apiKey): bool
    {
        // Usar Redis si está disponible, sino skip
        try {
            $redis = new Redis();
            $redis->connect(
                Configuration::get('REDIS_HOST') ?: '127.0.0.1',
                (int) (Configuration::get('REDIS_PORT') ?: 6379)
            );
            $key   = 'smart_api_rl:' . hash('sha256', $apiKey);
            $count = $redis->incr($key);
            if ($count === 1) {
                $redis->expire($key, 60);
            }
            return $count <= self::RATE_LIMIT;
        } catch (Exception $e) {
            return true; // Si Redis no está, no bloquear
        }
    }

    private function parsePath(): string
    {
        $uri    = $_SERVER['REQUEST_URI'] ?? '/';
        $prefix = '/api/smart/' . self::API_VERSION;
        $path   = substr($uri, strpos($uri, $prefix) + strlen($prefix));
        $path   = strtok($path, '?');
        return '/' . ltrim($path, '/');
    }

    private function parseRouteParams(string $method, string $path): ?array
    {
        $routes = self::ROUTES[$method] ?? [];
        foreach ($routes as $pattern => $handler) {
            $regex = '@^' . preg_replace('/\{[^}]+\}/', '([^/]+)', $pattern) . '$@';
            if (preg_match($regex, $path, $matches)) {
                // Extraer parámetros nombrados
                preg_match_all('/\{([^}]+)\}/', $pattern, $paramNames);
                foreach ($paramNames[1] as $i => $name) {
                    $_GET[$name] = $matches[$i + 1];
                }
                return $handler;
            }
        }
        return null;
    }

    private function requireParams(array $p, array $required): void
    {
        foreach ($required as $key) {
            if (!isset($p[$key]) || $p[$key] === '') {
                throw new SmartApiException("Parámetro requerido: {$key}", 400);
            }
        }
    }

    private function validateDate(string $date): string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new SmartApiException("Fecha inválida: {$date}. Formato: YYYY-MM-DD", 400);
        }
        return $date;
    }

    private function checkModules(): array
    {
        $modules = ['smart_frontdesk', 'smart_housekeeping', 'smart_dashboard', 'smart_guestprofile', 'smart_ratemanager'];
        $status  = [];
        foreach ($modules as $name) {
            $status[$name] = Module::isInstalled($name) ? 'installed' : 'not_installed';
        }
        return $status;
    }

    private function logApiCall(string $apiKey, string $method, string $path, int $status): void
    {
        // Log en DB para auditoría (tabla ligera, se purga cada 30 días)
        Db::getInstance()->execute(
            'INSERT IGNORE INTO `' . _DB_PREFIX_ . 'smart_api_log`
             (api_key_hash, method, path, status_code, ip_address, created_at)
             VALUES ("' . pSQL(substr(hash('sha256', $apiKey), 0, 16)) . '",
                     "' . pSQL($method) . '", "' . pSQL($path) . '", ' . (int) $status . ',
                     "' . pSQL($_SERVER['REMOTE_ADDR'] ?? '') . '", NOW())'
        );
    }

    private function respondSuccess(array $data): void
    {
        echo json_encode([
            'success'   => true,
            'data'      => $data,
            'meta'      => [
                'version'   => self::API_VERSION,
                'timestamp' => date('Y-m-d\TH:i:sP'),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function respondError(int $code, string $message): void
    {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error'   => [
                'code'    => $code,
                'message' => $message,
            ],
            'meta' => ['version' => self::API_VERSION],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Stubs para acciones de reservas
    private function listReservations(array $p): array   { return ['reservations' => [], 'total' => 0]; }
    private function getReservation(int $id): array       { return []; }
    private function createReservation(array $p): array   { return []; }
    private function processCheckIn(int $id, array $p): array  { return []; }
    private function processCheckOut(int $id, array $p): array { return []; }
    private function cancelReservation(int $id, array $p): array { return []; }
    private function handleRooms(string $a, array $p): array { return []; }
    private function handleRates(string $a, array $p): array  { return []; }
    private function handleNotifications(string $a, array $p): array { return []; }
}

/**
 * Excepción personalizada para la API.
 */
class SmartApiException extends RuntimeException
{
    public function __construct(string $message, int $code = 500)
    {
        parent::__construct($message, $code);
    }
}
