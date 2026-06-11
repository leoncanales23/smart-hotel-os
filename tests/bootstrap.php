<?php
/**
 * SmartHotelOS — PHPUnit Bootstrap
 * Grupo Smart de Administración
 */

// Definir constantes de QloApps para tests unitarios standalone
if (!defined('_PS_VERSION_'))   define('_PS_VERSION_',   '1.7.8');
if (!defined('_DB_PREFIX_'))    define('_DB_PREFIX_',    'ps_');
if (!defined('_PS_ROOT_DIR_'))  define('_PS_ROOT_DIR_',  __DIR__ . '/../core');
if (!defined('_PS_MODULE_DIR_'))define('_PS_MODULE_DIR_', __DIR__ . '/../smart-modules/');

// Cargar autoloader si existe
$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// Cargar clases stub para tests unitarios (sin QloApps real)
require_once __DIR__ . '/stubs/PrestaShopStubs.php';
