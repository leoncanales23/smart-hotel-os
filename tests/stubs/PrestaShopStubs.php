<?php
/**
 * SmartHotelOS — PrestaShop / QloApps Stubs
 * Grupo Smart de Administración
 *
 * Stubs mínimos de las clases de QloApps para poder ejecutar
 * tests unitarios sin instalar el framework completo.
 */

// ── Module stub ───────────────────────────────────────────────
if (!class_exists('Module')) {
    class Module {
        public string $name        = '';
        public string $tab         = '';
        public string $version     = '';
        public string $author      = '';
        public string $displayName = '';
        public string $description = '';
        public bool   $bootstrap   = true;
        public bool   $need_instance = false;
        public array  $ps_versions_compliancy = [];

        protected $context;
        protected $l_cache = [];

        public function __construct() {
            $this->context = Context::getContext();
        }

        public function install(): bool   { return true; }
        public function uninstall(): bool { return true; }

        public function l(string $string, string $specific = '', ?string $locale = null): string {
            return $string;
        }
        public function registerHook(array|string $hooks): bool { return true; }
        public function display(string $file, string $tpl): string { return ''; }

        public static function getInstanceByName(string $name): ?self {
            return null;
        }
        public static function isInstalled(string $name): bool { return false; }
    }
}

// ── ModuleAdminController stub ────────────────────────────────
if (!class_exists('ModuleAdminController')) {
    class ModuleAdminController {
        public static string $currentIndex = '/admin';
        protected string $token  = 'test_token';
        protected array  $errors = [];
        protected array  $confirmations = [];
        protected        $context;
        public   bool    $bootstrap = true;

        public function __construct() {
            $this->context = Context::getContext();
        }
        public function init(): void {}
        public function initContent(): void {}
        public function initToolbar(): void {}
        public function initPageHeaderToolbar(): void {}
        public function postProcess(): void {}
        protected function ajaxDie(string $value): never { echo $value; exit; }
    }
}

// ── Context stub ─────────────────────────────────────────────
if (!class_exists('Context')) {
    class Context {
        public $smarty;
        public $employee;
        public $link;
        public $language;
        public $shop;

        private static ?self $instance = null;

        public function __construct() {
            $this->smarty   = new SmartyStub();
            $this->employee = new stdClass();
            $this->employee->id = 1;
            $this->link     = new LinkStub();
            $this->language = new stdClass();
            $this->language->id = 1;
            $this->shop     = new stdClass();
            $this->shop->id = 1;
        }

        public static function getContext(): self {
            if (!self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }
    }
}

// ── Configuration stub ───────────────────────────────────────
if (!class_exists('Configuration')) {
    class Configuration {
        private static array $data = [];

        public static function get(string $key, mixed $default = false): mixed {
            return self::$data[$key] ?? $default;
        }

        public static function updateValue(string $key, mixed $value): bool {
            self::$data[$key] = $value;
            return true;
        }

        public static function reset(): void {
            self::$data = [];
        }
    }
}

// ── Db stub ──────────────────────────────────────────────────
if (!class_exists('Db')) {
    class Db {
        private static ?self $instance = null;
        private array $mockData = [];
        private int   $lastInsertId = 1;

        public static function getInstance(): self {
            if (!self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function execute(string $sql): bool { return true; }

        public function executeS(string $sql): array { return []; }

        public function getRow(string $sql): array|false { return false; }

        public function getValue(string $sql): mixed { return 0; }

        public function insert(string $table, array $data): bool {
            $this->lastInsertId++;
            return true;
        }

        public function update(string $table, array $data, string $where): bool { return true; }

        public function Insert_ID(): int { return $this->lastInsertId; }

        public function mockValue(mixed $value): void {
            // Para tests: configurar el siguiente valor de retorno
            $this->mockData[] = $value;
        }
    }
}

// ── Tools stub ───────────────────────────────────────────────
if (!class_exists('Tools')) {
    class Tools {
        private static array $postData = [];

        public static function getValue(string $key, mixed $default = false): mixed {
            return self::$postData[$key] ?? $_REQUEST[$key] ?? $default;
        }

        public static function isSubmit(string $key): bool {
            return isset($_POST[$key]) || isset(self::$postData[$key]);
        }

        public static function getAdminTokenLite(string $tab): string {
            return 'test_token_' . strtolower($tab);
        }

        public static function setPostData(array $data): void {
            self::$postData = $data;
        }

        public static function purifyHTML(string $html): string { return $html; }

        public static function htmlentitiesUTF8(string $str): string { return htmlentities($str, ENT_QUOTES, 'UTF-8'); }
    }
}

// ── Validate stub ────────────────────────────────────────────
if (!class_exists('Validate')) {
    class Validate {
        public static function isLoadedObject(mixed $obj): bool {
            return is_object($obj) && isset($obj->id) && $obj->id > 0;
        }
        public static function isEmail(string $email): bool { return filter_var($email, FILTER_VALIDATE_EMAIL) !== false; }
        public static function isInt(mixed $v): bool { return is_int($v) || ctype_digit((string) $v); }
    }
}

// ── Hook stub ────────────────────────────────────────────────
if (!class_exists('Hook')) {
    class Hook {
        private static array $dispatched = [];

        public static function exec(string $hookName, array $params = []): string {
            self::$dispatched[] = ['hook' => $hookName, 'params' => $params];
            return '';
        }

        public static function getDispatched(): array { return self::$dispatched; }
        public static function reset(): void { self::$dispatched = []; }
    }
}

// ── Language stub ────────────────────────────────────────────
if (!class_exists('Language')) {
    class Language {
        public static function getLanguages(): array {
            return [['id_lang' => 1, 'iso_code' => 'es', 'name' => 'Español']];
        }
    }
}

// ── Tab stub ─────────────────────────────────────────────────
if (!class_exists('Tab')) {
    class Tab {
        public int    $id        = 0;
        public int    $active    = 1;
        public string $class_name= '';
        public string $module    = '';
        public int    $id_parent = 0;
        public array  $name      = [];

        public function add(): bool { $this->id = rand(100, 9999); return true; }
        public function delete(): bool { return true; }

        public static function getIdFromClassName(string $className): int { return rand(1, 999); }
    }
}

// ── Customer stub ────────────────────────────────────────────
if (!class_exists('Customer')) {
    class Customer {
        public int    $id           = 0;
        public string $firstname    = '';
        public string $lastname     = '';
        public string $email        = '';
        public string $phone        = '';
        public string $phone_mobile = '';

        public function __construct(int $id = 0) {
            $this->id = $id;
            if ($id > 0) {
                $this->firstname = 'Test';
                $this->lastname  = 'Guest';
                $this->email     = 'guest@test.com';
            }
        }
    }
}

// ── HotelBookingDetail stub ───────────────────────────────────
if (!class_exists('HotelBookingDetail')) {
    class HotelBookingDetail {
        public int    $id              = 0;
        public int    $id_customer     = 0;
        public int    $id_room         = 0;
        public int    $id_room_type    = 0;
        public int    $booking_status  = 1;
        public string $booking_date_from = '';
        public string $booking_date_to   = '';
        public float  $total_paid_amount = 0.0;
        public string $checkin_date      = '';
        public string $checkout_date     = '';

        public function __construct(int $id = 0) { $this->id = $id; }
        public function update(): bool { return true; }
    }
}

// ── Mail stub ────────────────────────────────────────────────
if (!class_exists('Mail')) {
    class Mail {
        private static array $sent = [];

        public static function send(
            int $idLang, string $template, string $subject,
            array $vars, string $to, ?string $toName = null
        ): bool {
            self::$sent[] = compact('template', 'subject', 'to');
            return true;
        }

        public static function getSent(): array { return self::$sent; }
        public static function reset(): void { self::$sent = []; }
    }
}

// ── PrestaShopLogger stub ────────────────────────────────────
if (!class_exists('PrestaShopLogger')) {
    class PrestaShopLogger {
        private static array $logs = [];

        public static function addLog(
            string $message, int $severity = 1,
            mixed $errorCode = null, ?string $objectType = null,
            int $objectId = 0
        ): void {
            self::$logs[] = compact('message', 'severity', 'objectType', 'objectId');
        }

        public static function getLogs(): array { return self::$logs; }
        public static function reset(): void { self::$logs = []; }
    }
}

// ── Helper stubs ─────────────────────────────────────────────
class SmartyStub {
    private array $vars = [];
    public function assign(array|string $var, mixed $value = null): void {
        if (is_array($var)) { $this->vars = array_merge($this->vars, $var); }
        else { $this->vars[$var] = $value; }
    }
    public function fetch(string $tpl): string { return '<!-- tpl:' . $tpl . ' -->'; }
    public function getVar(string $key): mixed { return $this->vars[$key] ?? null; }
}

class LinkStub {
    public function getAdminLink(string $controller, bool $withToken = true): string {
        return '/admin/' . strtolower($controller) . ($withToken ? '?token=test' : '');
    }
    public function getModuleLink(string $module, string $controller = 'default'): string {
        return '/module/' . $module . '/' . $controller;
    }
}

/** Función de helpers globales de QloApps */
if (!function_exists('pSQL')) {
    function pSQL(string $string, bool $htmlOk = false): string {
        return str_replace(["'", "\\", "\0"], ["\'", "\\\\", ""], $string);
    }
}
if (!function_exists('bqSQL')) {
    function bqSQL(string $string): string {
        return str_replace('`', '\\`', $string);
    }
}
