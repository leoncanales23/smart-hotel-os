#!/usr/bin/env php
<?php
/**
 * SmartHotelOS — Seed de datos de demostración
 * Grupo Smart de Administración
 *
 * Carga datos de prueba para desarrollo y demos:
 * propiedad, tipos de habitación, habitaciones,
 * planes tarifarios, staff de HK y datos de muestra.
 *
 * @version 1.0.0
 */

if (!isset($pdo)) {
    // Ejecutado directamente
    if (!defined('_PS_VERSION_')) define('_PS_VERSION_', '1.0');
    require_once __DIR__ . '/../scripts/install-smart.php';
}

$prefix = 'ps_';

echo "\n  Cargando datos de demostración...\n";

try {
    // ── Propiedad ────────────────────────────────────────────
    $pdo->exec("INSERT IGNORE INTO `{$prefix}smart_property` 
        (id_property, name, code, address, stars, timezone, currency_code, active)
        VALUES (1, 'Hotel Smart Santiago', 'SMART01', 
                'Av. Providencia 1234, Santiago, Chile',
                5, 'America/Santiago', 'CLP', 1)
    ");

    // ── Staff de Housekeeping ────────────────────────────────
    $staffData = [
        ['María', 'González',  'housekeeper', '3,4,5'],
        ['Carmen', 'Rodríguez','housekeeper', '6,7,8'],
        ['Ana',    'Martínez', 'housekeeper', '9,10'],
        ['Rosa',   'López',    'housekeeper', '11,12'],
        ['Isabel', 'Sánchez',  'supervisor',  null],
        ['Pedro',  'Fernández','inspector',   null],
    ];

    foreach ($staffData as [$fn, $ln, $role, $floors]) {
        $pdo->exec("INSERT IGNORE INTO `{$prefix}smart_hk_staff`
            (id_property, firstname, lastname, role, floor_assign, active)
            VALUES (1, '{$fn}', '{$ln}', '{$role}', " . ($floors ? "'{$floors}'" : "NULL") . ", 1)
        ");
    }
    echo "  ✓ Staff de housekeeping (6 personas)\n";

    // ── Planes tarifarios ────────────────────────────────────
    $ratePlans = [
        ['BAR',     'BAR',       'Best Available Rate',    'BB', 1],
        ['ADVANCE', 'ADVANCE',   'Tarifa Anticipada -10%', 'BB', 1],
        ['NR',      'NON_REFUND','No Reembolsable -15%',   'RO', 0],
        ['PKG-BB',  'PACKAGE',   'Paquete B&B',            'BB', 1],
        ['CORP',    'CORPORATE', 'Tarifa Corporativa',      'RO', 1],
    ];

    foreach ($ratePlans as [$code, $type, $name, $meal, $refund]) {
        $pdo->exec("INSERT IGNORE INTO `{$prefix}smart_rate_plan`
            (id_property, code, name, plan_type, meal_plan, is_refundable, active)
            VALUES (1, '{$code}', '{$name}', '{$type}', '{$meal}', {$refund}, 1)
        ");
    }
    echo "  ✓ Planes tarifarios (5 planes)\n";

    // ── Config Yield Manager ─────────────────────────────────
    $pdo->exec("INSERT IGNORE INTO `{$prefix}smart_yield_config`
        (id_property, enabled, base_occupancy, increment_high, decrement_low,
         max_price_floor, max_price_ceil, auto_apply)
        VALUES (1, 0, 70.00, 20.00, 15.00, 60.00, 150.00, 0)
    ");
    echo "  ✓ Yield Manager configurado\n";

    // ── Targets del dashboard ────────────────────────────────
    $pdo->exec("INSERT IGNORE INTO `{$prefix}smart_dashboard_targets`
        (id_property, target_occupancy, target_adr, target_revpar, target_nps,
         budget_rooms, budget_fnb, budget_total)
        VALUES (1, 75.00, 95000.00, 71250.00, 72.00,
                120000000, 25000000, 150000000)
    ");
    echo "  ✓ Targets del dashboard configurados\n";

    // ── Categorías y menú del restaurante ───────────────────
    $categories = [
        ['Desayunos',     1],
        ['Entradas',      2],
        ['Platos de fondo',3],
        ['Postres',       4],
        ['Bebidas',       5],
        ['Room Service',  6],
    ];
    foreach ($categories as [$name, $sort]) {
        $pdo->exec("INSERT IGNORE INTO `{$prefix}smart_fb_menu_category`
            (name, sort_order, active)
            VALUES ('{$name}', {$sort}, 1)
        ");
    }

    $catIds = [];
    $rows = $pdo->query("SELECT id, name FROM `{$prefix}smart_fb_menu_category`")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) { $catIds[$r['name']] = $r['id']; }

    $menuItems = [
        ['Desayuno Americano',        $catIds['Desayunos']??1,       8900,  null, 1, 1, 0, 'Huevos, tocino, tostadas, jugo'],
        ['Avocado Toast',             $catIds['Desayunos']??1,       7500,  null, 1, 0, 0, 'Pan artesanal, palta, tomate cherry'],
        ['Ceviche de Reineta',        $catIds['Entradas']??2,        12000, 13500, 0, 1, 0, 'Con leche de tigre y canchita'],
        ['Salmón a la Plancha',       $catIds['Platos de fondo']??3, 18500, 20000, 0, 1, 0, 'Con puré de coliflor y espárragos'],
        ['Lomo Vetado',               $catIds['Platos de fondo']??3, 22000, 24000, 0, 0, 0, 'Punto cocción a elección, papas gajo'],
        ['Pasta Primavera',           $catIds['Platos de fondo']??3, 14000, 15500, 0, 1, 1, 'Vegetales de temporada, pesto'],
        ['Crème Brûlée',              $catIds['Postres']??4,         6500,  7500, 0, 1, 1, 'Vainilla Bourbon'],
        ['Pisco Sour',                $catIds['Bebidas']??5,         5500,  null, 0, 0, 0, 'Tradicional chileno'],
        ['Agua Mineral 500ml',        $catIds['Bebidas']??5,         2200,  2500, 1, 0, 0, 'Con o sin gas'],
        ['Sandwich Club',             $catIds['Room Service']??6,    9500,  null, 0, 0, 0, 'Pollo, palta, tocino, 24/7'],
    ];

    foreach ($menuItems as [$name, $catId, $price, $priceRS, $veg, $gf, $vegan, $desc]) {
        $priceRSVal = $priceRS ? $priceRS : 'NULL';
        $pdo->exec("INSERT IGNORE INTO `{$prefix}smart_fb_menu_item`
            (id_category, name, description, price, price_room_svc,
             is_vegetarian, is_gluten_free, is_vegan, available, active, prep_time_min)
            VALUES ({$catId}, '{$name}', '{$desc}', {$price}, {$priceRSVal},
                    {$veg}, {$gf}, {$vegan}, 1, 1, 20)
        ");
    }
    echo "  ✓ Menú del restaurante (10 ítems)\n";

    // ── Servicios del spa ────────────────────────────────────
    $spaServices = [
        ['Masaje Relajante 60min',     'MASSAGE',  60,  75000, null,   0],
        ['Masaje Relajante 90min',     'MASSAGE',  90,  110000,null,   0],
        ['Masaje de Pareja 60min',     'MASSAGE',  60,  140000,140000, 1],
        ['Facial Hidratante',          'FACIAL',   75,  65000, null,   0],
        ['Exfoliación Corporal',       'BODY',     60,  70000, null,   0],
        ['Sesión de Yoga',             'YOGA',     60,  25000, null,   0],
        ['Entrenamiento Personal',     'FITNESS',  60,  45000, null,   0],
        ['Paquete Wellness 3h',        'PACKAGE',  180, 195000,380000, 1],
    ];

    foreach ($spaServices as [$name, $type, $dur, $price, $priceCpl, $couples]) {
        $priceC = $priceCpl ? $priceCpl : 'NULL';
        $pdo->exec("INSERT IGNORE INTO `{$prefix}smart_spa_service`
            (id_property, name, service_type, duration_min, price, price_couple,
             is_couples, active, sort_order)
            VALUES (1, '{$name}', '{$type}', {$dur}, {$price}, {$priceC}, {$couples}, 1, 0)
        ");
    }
    echo "  ✓ Servicios del spa (8 tratamientos)\n";

    // ── Instalación del spa ──────────────────────────────────
    $facilities = [
        ['Sala de Masajes 1',  'TREATMENT_ROOM', 1],
        ['Sala de Masajes 2',  'TREATMENT_ROOM', 1],
        ['Sala de Parejas',    'TREATMENT_ROOM', 2],
        ['Sala Facial',        'TREATMENT_ROOM', 1],
        ['Piscina Temperada',  'POOL',           20],
        ['Sauna Finlandesa',   'SAUNA',          8],
        ['Jacuzzi',            'JACUZZI',        4],
        ['Gimnasio',           'GYM',            30],
    ];
    foreach ($facilities as [$name, $type, $cap]) {
        $pdo->exec("INSERT IGNORE INTO `{$prefix}smart_spa_facility`
            (id_property, name, facility_type, capacity, active)
            VALUES (1, '{$name}', '{$type}', {$cap}, 1)
        ");
    }
    echo "  ✓ Instalaciones del spa (8 espacios)\n";

    // ── Terapeutas ───────────────────────────────────────────
    $therapists = [
        ['Valentina', 'Morales', 'F', '["MASSAGE","BODY","FACIAL"]'],
        ['Claudia',   'Torres',  'F', '["MASSAGE","YOGA","WELLNESS"]'],
        ['Diego',     'Ramírez', 'M', '["MASSAGE","FITNESS","BODY"]'],
        ['Sofía',     'Castro',  'F', '["FACIAL","BODY","MASSAGE"]'],
    ];
    foreach ($therapists as [$fn, $ln, $gender, $spec]) {
        $pdo->exec("INSERT IGNORE INTO `{$prefix}smart_spa_therapist`
            (id_property, firstname, lastname, gender, specialties, active)
            VALUES (1, '{$fn}', '{$ln}', '{$gender}', '{$spec}', 1)
        ");
    }
    echo "  ✓ Terapeutas del spa (4 personas)\n";

    // ── API Key de desarrollo ────────────────────────────────
    $devToken = 'dev_smarthotel_' . bin2hex(random_bytes(8));
    $devHash  = hash('sha256', $devToken);
    $pdo->exec("INSERT IGNORE INTO `{$prefix}smart_api_key`
        (id_property, name, api_key, permissions, active)
        VALUES (1, 'Desarrollo (DEV)', '{$devHash}', 
                '[\"*\"]', 1)
    ");

    // Guardar token legible en .env.dev para referencia
    $devFile = __DIR__ . '/../.env.dev';
    file_put_contents($devFile, "# SmartHotelOS - Tokens de desarrollo\n");
    file_put_contents($devFile, "SMART_API_DEV_TOKEN={$devToken}\n", FILE_APPEND);
    echo "  ✓ API Key de desarrollo creada → .env.dev\n";

    echo "\n  ✓ Seed completado exitosamente\n\n";

} catch (PDOException $e) {
    echo "  ✗ Error en seed: " . $e->getMessage() . "\n";
}
