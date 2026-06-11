#!/usr/bin/env php
<?php
/**
 * SmartHotelOS — Instalador Maestro
 * Grupo Smart de Administración
 *
 * Instala y configura todos los módulos del sistema en el orden correcto.
 * Ejecutar una sola vez durante el setup inicial.
 *
 * Uso:
 *   php scripts/install-smart.php [--env=production] [--skip-demo]
 *
 * @version 1.0.0
 */

define('SMART_INSTALLER_VERSION', '1.0.0');
define('SMART_INSTALLER_START',   microtime(true));

// ── Colores para CLI ──────────────────────────────────────────
function c(string $text, string $color = 'reset'): string {
    $colors = [
        'gold'    => "\033[33m",
        'green'   => "\033[32m",
        'red'     => "\033[31m",
        'blue'    => "\033[34m",
        'dim'     => "\033[2m",
        'bold'    => "\033[1m",
        'reset'   => "\033[0m",
    ];
    return ($colors[$color] ?? '') . $text . $colors['reset'];
}

function log(string $msg, string $level = 'info'): void {
    $prefix = match($level) {
        'ok'    => c('  ✓ ', 'green'),
        'error' => c('  ✗ ', 'red'),
        'warn'  => c('  ⚠ ', 'gold'),
        'step'  => c('  → ', 'blue'),
        default => c('    ', 'dim'),
    };
    echo $prefix . $msg . PHP_EOL;
}

function section(string $title): void {
    echo PHP_EOL . c('  ══════════════════════════════════════════', 'dim') . PHP_EOL;
    echo c("  " . strtoupper($title), 'gold') . PHP_EOL;
    echo c('  ══════════════════════════════════════════', 'dim') . PHP_EOL;
}

// ── Banner ────────────────────────────────────────────────────
echo PHP_EOL;
echo c('  ╔═══════════════════════════════════════════╗', 'gold') . PHP_EOL;
echo c('  ║     SmartHotelOS — Instalador Maestro      ║', 'gold') . PHP_EOL;
echo c('  ║    Grupo Smart de Administración v1.0.0    ║', 'gold') . PHP_EOL;
echo c('  ╚═══════════════════════════════════════════╝', 'gold') . PHP_EOL;
echo PHP_EOL;

// ── Parsear argumentos ────────────────────────────────────────
$opts     = getopt('', ['env:', 'skip-demo', 'dry-run', 'help']);
$env      = $opts['env']   ?? 'development';
$skipDemo = isset($opts['skip-demo']);
$dryRun   = isset($opts['dry-run']);
$help     = isset($opts['help']);

if ($help) {
    echo c("  Uso: php scripts/install-smart.php [opciones]\n\n", 'bold');
    echo "  --env=development|staging|production   Entorno de instalación\n";
    echo "  --skip-demo                            No instalar datos de prueba\n";
    echo "  --dry-run                              Mostrar qué se haría sin ejecutar\n";
    echo "  --help                                 Mostrar esta ayuda\n\n";
    exit(0);
}

log("Entorno: " . c($env, 'gold'), 'step');
log("Modo: " . ($dryRun ? c("simulación (dry-run)", 'gold') : "instalación real"), 'step');

// ── Verificar entorno PHP ─────────────────────────────────────
section('Verificación del entorno');

$requirements = [
    'PHP >= 8.2'    => PHP_VERSION_ID >= 80200,
    'PDO MySQL'     => extension_loaded('pdo_mysql'),
    'cURL'          => extension_loaded('curl'),
    'GD'            => extension_loaded('gd'),
    'JSON'          => extension_loaded('json'),
    'Mbstring'      => extension_loaded('mbstring'),
    'OpenSSL'       => extension_loaded('openssl'),
    'Redis ext'     => extension_loaded('redis'),
];

$allOk = true;
foreach ($requirements as $req => $ok) {
    if ($ok) {
        log($req, 'ok');
    } else {
        log($req . c(' — FALTANTE', 'red'), 'error');
        if (!str_contains($req, 'Redis')) { // Redis no es crítico
            $allOk = false;
        }
    }
}

if (!$allOk) {
    log("Requisitos críticos no cumplidos. Instale las extensiones PHP faltantes.", 'error');
    exit(1);
}

// ── Verificar archivos de configuración ──────────────────────
section('Configuración');

$envFile = __DIR__ . '/../.env';
if (!file_exists($envFile)) {
    if (file_exists(__DIR__ . '/../.env.example')) {
        if (!$dryRun) {
            copy(__DIR__ . '/../.env.example', $envFile);
        }
        log(".env creado desde .env.example — " . c("EDÍTELO antes de continuar", 'gold'), 'warn');
    } else {
        log(".env.example no encontrado", 'error');
        exit(1);
    }
} else {
    log(".env encontrado", 'ok');
}

// Cargar variables de entorno desde .env
$envVars = [];
foreach (file($envFile) as $line) {
    $line = trim($line);
    if (empty($line) || str_starts_with($line, '#')) continue;
    if (str_contains($line, '=')) {
        [$key, $val] = explode('=', $line, 2);
        $envVars[trim($key)] = trim($val);
        putenv(trim($key) . '=' . trim($val));
    }
}
log("Variables de entorno cargadas: " . count($envVars), 'ok');

// ── Verificar conexión a base de datos ───────────────────────
section('Base de datos');

$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbName = getenv('DB_NAME') ?: 'smart_hotel_db';
$dbUser = getenv('DB_USER') ?: 'smart_user';
$dbPass = getenv('DB_PASSWORD') ?: '';
$dbPort = getenv('DB_PORT') ?: '3306';

try {
    if (!$dryRun) {
        $pdo = new PDO(
            "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4",
            $dbUser, $dbPass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        log("Conectado a MySQL ({$dbHost}:{$dbPort})", 'ok');

        // Crear base de datos si no existe
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` 
                    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$dbName}`");
        log("Base de datos '{$dbName}' verificada/creada", 'ok');
    } else {
        log("[DRY-RUN] Conexión MySQL: {$dbHost}:{$dbPort} / {$dbName}", 'ok');
    }
} catch (PDOException $e) {
    log("Error de conexión: " . $e->getMessage(), 'error');
    exit(1);
}

// ── Instalar módulos Smart en orden de dependencias ──────────
section('Instalación de módulos');

/**
 * Orden de instalación:
 * 1. Notificaciones (independiente, necesario por otros)
 * 2. Dashboard (base, crea tablas comunes como smart_room_charge y smart_api_key)
 * 3. Guest Profile (independiente)
 * 4. Rate Manager (independiente)
 * 5. Front Desk (depende de Dashboard para alertas)
 * 6. Housekeeping (depende de Front Desk para hooks)
 * 7. Maintenance (depende de Housekeeping para room status)
 * 8. Restaurant (depende de Dashboard para cargos)
 * 9. Spa (depende de Dashboard para cargos)
 * 10. Concierge (depende de Notificaciones y Front Desk)
 */
$modules = [
    'smart_notifications' => 'Hub de notificaciones (WhatsApp, email, push)',
    'smart_dashboard'     => 'Dashboard ejecutivo + KPIs + API tables',
    'smart_guestprofile'  => 'CRM de huéspedes 360°',
    'smart_ratemanager'   => 'Gestión de tarifas y yield',
    'smart_frontdesk'     => 'Front Desk — check-in/out digital',
    'smart_housekeeping'  => 'Housekeeping — panel de pisos RT',
    'smart_maintenance'   => 'Mantenimiento preventivo y correctivo',
    'smart_restaurant'    => 'Restaurante & F&B POS',
    'smart_spa'           => 'Spa & Wellness',
    'smart_concierge'     => 'Concierge digital y WhatsApp bot',
];

$installed = 0;
$errors    = 0;

foreach ($modules as $moduleName => $description) {
    $modulePath = __DIR__ . "/../smart-modules/{$moduleName}/{$moduleName}.php";

    if (!file_exists($modulePath)) {
        log("{$moduleName} — archivo no encontrado: {$modulePath}", 'warn');
        continue;
    }

    if ($dryRun) {
        log("[DRY-RUN] {$moduleName} — {$description}", 'ok');
        $installed++;
        continue;
    }

    log("Instalando {$moduleName}...", 'step');

    // Cargar el módulo en el contexto de QloApps
    if (class_exists('Module')) {
        $module = Module::getInstanceByName($moduleName);
        if ($module) {
            if (!$module->isInstalled($moduleName)) {
                if ($module->install()) {
                    log("{$moduleName} — {$description}", 'ok');
                    $installed++;
                } else {
                    log("{$moduleName} — falló la instalación", 'error');
                    $errors++;
                }
            } else {
                log("{$moduleName} — ya instalado, saltando", 'warn');
                $installed++;
            }
        }
    } else {
        // Fuera del contexto de QloApps: solo ejecutar los SQLs directamente
        $sqlFile = __DIR__ . "/../smart-modules/{$moduleName}/sql/install.php";
        if (file_exists($sqlFile)) {
            // Simular _DB_PREFIX_
            if (!defined('_DB_PREFIX_')) define('_DB_PREFIX_', 'ps_');
            if (!defined('_PS_VERSION_')) define('_PS_VERSION_', '1.7.8');

            // Inyectar PDO simulado
            try {
                ob_start();
                // Los SQL files retornan true/false
                // En este contexto standalone simplemente los ejecutamos vía PDO
                $sqlContent = file_get_contents($sqlFile);

                // Extraer CREATE TABLE statements
                preg_match_all(
                    '/CREATE TABLE IF NOT EXISTS[^;]+;/s',
                    str_replace('_DB_PREFIX_', "ps_", $sqlContent),
                    $matches
                );
                foreach ($matches[0] as $createSql) {
                    if (isset($pdo)) {
                        $pdo->exec($createSql);
                    }
                }
                ob_end_clean();
                log("{$moduleName} — tablas SQL creadas", 'ok');
                $installed++;
            } catch (Exception $e) {
                ob_end_clean();
                log("{$moduleName} — error SQL: " . $e->getMessage(), 'error');
                $errors++;
            }
        } else {
            log("{$moduleName} — sin archivo SQL (ok, tablas en clase)", 'ok');
            $installed++;
        }
    }
}

// ── Configuración post-instalación ───────────────────────────
section('Configuración post-instalación');

$postConfigs = [
    ['SMART_API_MASTER_KEY',    bin2hex(random_bytes(32)),     'API master key generada'],
    ['DEFAULT_PROPERTY_ID',     '1',                           'Propiedad por defecto'],
    ['DEFAULT_PROPERTY_CODE',   'SMART01',                     'Código de propiedad'],
    ['DEFAULT_TIMEZONE',        'America/Santiago',            'Zona horaria'],
    ['DEFAULT_CURRENCY',        'CLP',                         'Moneda por defecto'],
    ['SMART_FD_CHECKIN_TIME',   '15:00',                       'Hora de check-in'],
    ['SMART_FD_CHECKOUT_TIME',  '12:00',                       'Hora de check-out'],
    ['SMART_CC_BOT_NAME',       'Sofia',                       'Nombre del bot concierge'],
];

foreach ($postConfigs as [$key, $value, $desc]) {
    if ($dryRun) {
        log("[DRY-RUN] {$key} = {$value}", 'ok');
        continue;
    }

    if (class_exists('Configuration')) {
        if (!Configuration::get($key)) {
            Configuration::updateValue($key, $value);
        }
    } else if (isset($pdo) && str_starts_with($key, 'SMART_')) {
        // Guardar en tabla de configuración de QloApps
        try {
            $stmt = $pdo->prepare(
                "INSERT IGNORE INTO `ps_configuration` (name, value, date_add, date_upd)
                 VALUES (?, ?, NOW(), NOW())"
            );
            $stmt->execute([$key, $value]);
        } catch (PDOException $e) {
            // La tabla puede no existir en contexto standalone
        }
    }
    log("{$desc}", 'ok');
}

// ── Datos de demostración ─────────────────────────────────────
if (!$skipDemo && !$dryRun) {
    section('Datos de demostración');
    $demoScript = __DIR__ . '/seed-demo-data.php';
    if (file_exists($demoScript)) {
        log("Ejecutando seed-demo-data.php...", 'step');
        include $demoScript;
        log("Datos de demo cargados", 'ok');
    } else {
        log("seed-demo-data.php no encontrado — omitiendo", 'warn');
    }
}

// ── Resumen final ─────────────────────────────────────────────
section('Resumen de instalación');

$elapsed = round(microtime(true) - SMART_INSTALLER_START, 2);

log("Módulos instalados: " . c($installed, 'green'), 'ok');
if ($errors > 0) {
    log("Módulos con error: " . c($errors, 'red'), 'error');
}
log("Tiempo total: {$elapsed}s", 'ok');
log("Entorno: {$env}", 'ok');

echo PHP_EOL;
if ($errors === 0) {
    echo c('  ✓ SmartHotelOS instalado correctamente', 'green') . PHP_EOL;
    echo c('  → Admin: /admin-smart', 'blue') . PHP_EOL;
    echo c('  → API:   /api/smart/v1/health', 'blue') . PHP_EOL;
} else {
    echo c('  ⚠ Instalación completada con ' . $errors . ' error(es). Revise los logs.', 'gold') . PHP_EOL;
}
echo PHP_EOL;

exit($errors > 0 ? 1 : 0);
