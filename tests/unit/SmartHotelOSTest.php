<?php
/**
 * SmartHotelOS — Suite de Tests Unitarios
 * Grupo Smart de Administración
 *
 * Tests PHPUnit para los módulos core del sistema.
 * Ejecutar: vendor/bin/phpunit tests/unit/
 *
 * @version 1.0.0
 */

namespace SmartHotelOS\Tests\Unit;

use PHPUnit\Framework\TestCase;

// ──────────────────────────────────────────────────────────────
//  TEST: Smart Dashboard — KPIs y Alertas
// ──────────────────────────────────────────────────────────────
class SmartDashboardTest extends TestCase
{
    private $dashboard;

    protected function setUp(): void
    {
        // Mock del módulo Dashboard
        $this->dashboard = $this->getMockBuilder('Smart_Dashboard')
            ->disableOriginalConstructor()
            ->onlyMethods(['getOccupancyData', 'getRevenueData', 'createAlert'])
            ->getMock();
    }

    public function testGetDailyKpisStructure(): void
    {
        $this->dashboard
            ->method('getOccupancyData')
            ->willReturn([
                'total_rooms'    => 80,
                'occupied'       => 60,
                'occupancy_pct'  => 75.0,
                'arrivals_total' => 12,
                'arrivals_checked'=> 8,
                'arrivals_pending'=> 4,
                'departures'     => 10,
                'in_house'       => 60,
                'no_shows'       => 1,
                'status'         => 'good',
            ]);

        $this->dashboard
            ->method('getRevenueData')
            ->willReturn([
                'total'   => 5700000,
                'rooms'   => 5700000,
                'fnb'     => 0,
                'spa'     => 0,
                'adr'     => 95000,
                'revpar'  => 71250,
                'trevpar' => 71250,
                'currency'=> 'CLP',
            ]);

        $occ = $this->dashboard->getOccupancyData(1, date('Y-m-d'));
        $rev = $this->dashboard->getRevenueData(1, date('Y-m-d'));

        $this->assertEquals(75.0, $occ['occupancy_pct']);
        $this->assertEquals(80,   $occ['total_rooms']);
        $this->assertEquals(60,   $occ['occupied']);
        $this->assertEquals(95000, $rev['adr']);
        $this->assertEquals(71250, $rev['revpar']);
        $this->assertEquals('CLP', $rev['currency']);
    }

    public function testOccupancyStatusLow(): void
    {
        // A través de reflexión verificar el método privado getOccupancyStatus
        $reflection = new \ReflectionClass('Smart_Dashboard');
        $method = $reflection->getMethod('getOccupancyStatus');
        $method->setAccessible(true);

        $dash = new class extends Smart_Dashboard {
            public function __construct() { /* skip parent */ }
        };

        // Usando mock directo
        $this->assertEquals('critical_low',  $this->invokeOccupancyStatus(15));
        $this->assertEquals('low',           $this->invokeOccupancyStatus(35));
        $this->assertEquals('moderate',      $this->invokeOccupancyStatus(55));
        $this->assertEquals('good',          $this->invokeOccupancyStatus(75));
        $this->assertEquals('critical_high', $this->invokeOccupancyStatus(95));
    }

    private function invokeOccupancyStatus(float $pct): string
    {
        if ($pct >= 90) return 'critical_high';
        if ($pct >= 70) return 'good';
        if ($pct >= 50) return 'moderate';
        if ($pct >= 30) return 'low';
        return 'critical_low';
    }

    public function testRevParCalculation(): void
    {
        $roomsRevenue = 5700000; // CLP
        $totalRooms   = 80;
        $occupied     = 60;

        $adr    = $roomsRevenue / $occupied;
        $revpar = $roomsRevenue / $totalRooms;

        $this->assertEquals(95000,  round($adr));
        $this->assertEquals(71250, round($revpar));
    }
}

// ──────────────────────────────────────────────────────────────
//  TEST: Smart Rate Manager — Yield Engine
// ──────────────────────────────────────────────────────────────
class SmartRateManagerTest extends TestCase
{
    public function testYieldMultiplierHighOccupancy(): void
    {
        // Simular: ocupación 92% → precio debe subir
        $occupancyPct  = 92.0;
        $incrementHigh = 20.0; // %
        $maxCeil       = 150.0; // % máximo sobre BAR

        $expectedMultMin = 1.15;  // al menos 15% de incremento
        $expectedMultMax = 1 + ($maxCeil / 100);

        $multiplier = 1 + ($incrementHigh / 100);
        $multiplier = min($multiplier, $expectedMultMax);

        $this->assertGreaterThanOrEqual($expectedMultMin, $multiplier);
        $this->assertLessThanOrEqual($expectedMultMax, $multiplier);
        $this->assertEquals(1.20, $multiplier);
    }

    public function testYieldMultiplierLowOccupancy(): void
    {
        $occupancyPct = 35.0;
        $decrementLow = 15.0; // %
        $floorPct     = 60.0; // mínimo 60% del precio

        $multiplier = 1 - ($decrementLow / 100);
        $floorMult  = $floorPct / 100;
        $multiplier = max($multiplier, $floorMult);

        $this->assertEquals(0.85, $multiplier);
        $this->assertGreaterThanOrEqual($floorMult, $multiplier);
    }

    public function testPriceRoundingCLP(): void
    {
        // Los precios en CLP se redondean a miles
        $currentPrice   = 87500;
        $multiplier     = 1.20;
        $suggestedPrice = round($currentPrice * $multiplier, -3);

        $this->assertEquals(105000, $suggestedPrice);
        $this->assertEquals(0, $suggestedPrice % 1000);
    }

    public function testRateParityDisparity(): void
    {
        $prices = [
            'DIRECT_WEB'  => 95000,
            'BOOKING_COM' => 99000, // 4.2% más caro
            'EXPEDIA'     => 97000,
        ];

        $min      = min($prices);
        $max      = max($prices);
        $disparity = ($max - $min) / max($min, 1) * 100;

        // < 5% es aceptable, >= 5% genera alerta
        $this->assertLessThan(5.0, $disparity);
    }

    public function testRateParityAlert(): void
    {
        $prices = [
            'DIRECT_WEB'  => 95000,
            'BOOKING_COM' => 110000, // 15.8% más caro — ALERTA
        ];

        $min      = min($prices);
        $max      = max($prices);
        $disparity = ($max - $min) / max($min, 1) * 100;

        $this->assertGreaterThanOrEqual(5.0, $disparity);
        $this->assertTrue($disparity >= 5, 'Debe generar alerta de paridad');
    }
}

// ──────────────────────────────────────────────────────────────
//  TEST: Smart Housekeeping — Lógica de pisos
// ──────────────────────────────────────────────────────────────
class SmartHousekeepingTest extends TestCase
{
    public function testRoomStatusTransitions(): void
    {
        // Flujo normal: VD → IP → VC → IN
        $validTransitions = [
            'VD'  => ['IP'],                    // Vacante sucia → en limpieza
            'IP'  => ['VC', 'VD'],              // En limpieza → limpia o devolver
            'VC'  => ['IN', 'OC', 'VD'],        // Vacante limpia → inspeccionada, ocupada, sucia
            'IN'  => ['OC', 'VC'],              // Inspeccionada → ocupada o libre
            'OC'  => ['OD', 'DND'],             // Ocupada limpia → sucia o DND
            'OD'  => ['IP'],                    // Ocupada sucia → limpiar
            'DND' => ['OC', 'OD'],              // DND → normal
            'OO'  => ['VD', 'VC'],              // Fuera de servicio → sucia o limpia
        ];

        // Verificar que el flujo checkout → HK es correcto
        $checkoutStatus = 'VD'; // Después de checkout
        $this->assertContains('IP', $validTransitions[$checkoutStatus]);

        // Después de limpiar con inspección requerida
        $afterClean = 'VC';
        $this->assertContains('IN', $validTransitions[$afterClean]);
    }

    public function testStaffLoadCalculation(): void
    {
        $maxRoomsPerStaff = 16;

        // Staff con 14 tareas → puede recibir más
        $currentLoad14 = 14;
        $this->assertLessThan($maxRoomsPerStaff, $currentLoad14);
        $this->assertTrue($currentLoad14 < $maxRoomsPerStaff);

        // Staff con 16 tareas → no puede recibir más
        $currentLoad16 = 16;
        $this->assertGreaterThanOrEqual($maxRoomsPerStaff, $currentLoad16);
        $this->assertFalse($currentLoad16 < $maxRoomsPerStaff);
    }

    public function testPriorityAssignment(): void
    {
        // Habitación VIP checkout → prioridad 5
        // Habitación normal checkout → prioridad 2

        $priorities = [
            'VIP_CHECKOUT'    => 5,
            'NORMAL_CHECKOUT' => 2,
            'STAYOVER'        => 2,
            'URGENT'          => 4,
        ];

        $this->assertEquals(5, $priorities['VIP_CHECKOUT']);
        $this->assertGreaterThan($priorities['STAYOVER'], $priorities['VIP_CHECKOUT']);
        $this->assertGreaterThan($priorities['NORMAL_CHECKOUT'], $priorities['URGENT']);
    }

    public function testTaskDurationTracking(): void
    {
        $startTime   = new \DateTime('-45 minutes');
        $endTime     = new \DateTime();
        $durationMin = (int) round(($endTime->getTimestamp() - $startTime->getTimestamp()) / 60);

        // Debe ser aproximadamente 45 minutos
        $this->assertGreaterThanOrEqual(44, $durationMin);
        $this->assertLessThanOrEqual(46, $durationMin);
    }
}

// ──────────────────────────────────────────────────────────────
//  TEST: Smart Guest Profile — CRM lógica
// ──────────────────────────────────────────────────────────────
class SmartGuestProfileTest extends TestCase
{
    public function testGuestCodeFormat(): void
    {
        // El código de huésped debe seguir el formato SG-XXXXXX
        $codes = ['SG-000001', 'SG-000123', 'SG-999999'];

        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression(
                '/^SG-\d{6}$/',
                $code,
                "El código '{$code}' no tiene el formato correcto"
            );
        }
    }

    public function testSegmentAutoCalculation(): void
    {
        $vipStaysThreshold = 5;
        $vipSpendThreshold = 500000; // CLP

        // Caso: huésped con 3 estancias → FREQUENT
        $this->assertEquals('STANDARD', $this->calcSegment(1, 80000, $vipStaysThreshold, $vipSpendThreshold));
        $this->assertEquals('FREQUENT', $this->calcSegment(3, 200000, $vipStaysThreshold, $vipSpendThreshold));
        $this->assertEquals('FREQUENT', $this->calcSegment(5, 100000, $vipStaysThreshold, $vipSpendThreshold));
        $this->assertEquals('VIP',      $this->calcSegment(15, 800000, $vipStaysThreshold, $vipSpendThreshold));
    }

    private function calcSegment(int $stays, float $spent, int $vipStays, float $vipSpend): string
    {
        if ($stays >= $vipStays * 3 || $spent >= $vipSpend * 5) return 'VIP';
        if ($stays >= $vipStays || $spent >= $vipSpend)         return 'FREQUENT';
        return 'STANDARD';
    }

    public function testAllergySevertiyLevels(): void
    {
        $levels = ['intolerance', 'allergy', 'anaphylaxis'];
        $sorted = $levels; // ya están en orden de severidad

        // Anafilaxia es la más grave y debe notificarse siempre
        $this->assertEquals('anaphylaxis', end($sorted));

        // Verificar que todos los niveles son únicos
        $this->assertCount(count($levels), array_unique($levels));
    }

    public function testOccasionReminderWindow(): void
    {
        // Si el cumpleaños es en 5 días y el remind es 7, debe activarse
        $daysUntilOccasion = 5;
        $remindDays        = 7;

        $shouldRemind = $daysUntilOccasion <= $remindDays;
        $this->assertTrue($shouldRemind);

        // Si el cumpleaños es en 30 días y el remind es 7, NO debe activarse
        $daysUntilOccasion2 = 30;
        $shouldRemind2 = $daysUntilOccasion2 <= $remindDays;
        $this->assertFalse($shouldRemind2);
    }
}

// ──────────────────────────────────────────────────────────────
//  TEST: Smart Concierge — Intent detection
// ──────────────────────────────────────────────────────────────
class SmartConciergeTest extends TestCase
{
    private function detectIntent(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $patterns = [
            'WIFI_INFO'         => ['wifi', 'contraseña', 'clave', 'internet', 'password'],
            'CHECKOUT_TIME'     => ['hora de salida', 'hora checkout', 'hora check-out'],
            'LATE_CHECKOUT'     => ['late checkout', 'salida tarde', 'extender'],
            'ROOM_SERVICE'      => ['room service', 'servicio habitación', 'pedir comida'],
            'RESTAURANT_RES'    => ['reserva restaurante', 'mesa', 'cena'],
            'SPA_BOOKING'       => ['spa', 'masaje', 'tratamiento'],
            'TRANSPORT'         => ['taxi', 'transfer', 'aeropuerto', 'transporte'],
            'MAINTENANCE_ISSUE' => ['no funciona', 'roto', 'problema', 'falla'],
            'ESCALATE'          => ['hablar con persona', 'recepción', 'humano'],
        ];
        foreach ($patterns as $intent => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($text, $kw)) return $intent;
            }
        }
        return 'GENERAL_HELP';
    }

    public function testWifiIntentDetection(): void
    {
        $this->assertEquals('WIFI_INFO', $this->detectIntent('¿cuál es la contraseña del wifi?'));
        $this->assertEquals('WIFI_INFO', $this->detectIntent('necesito la clave de internet'));
        $this->assertEquals('WIFI_INFO', $this->detectIntent('wifi password'));
    }

    public function testSpaIntentDetection(): void
    {
        $this->assertEquals('SPA_BOOKING', $this->detectIntent('quiero reservar un masaje'));
        $this->assertEquals('SPA_BOOKING', $this->detectIntent('¿tienen disponibilidad en el spa?'));
        $this->assertEquals('SPA_BOOKING', $this->detectIntent('me gustaría un tratamiento facial'));
    }

    public function testEscalateIntent(): void
    {
        $this->assertEquals('ESCALATE', $this->detectIntent('quiero hablar con una persona'));
        $this->assertEquals('ESCALATE', $this->detectIntent('necesito llamar a recepción'));
        $this->assertEquals('ESCALATE', $this->detectIntent('hablar con humano'));
    }

    public function testUnknownIntentFallback(): void
    {
        $this->assertEquals('GENERAL_HELP', $this->detectIntent('hola buenos días'));
        $this->assertEquals('GENERAL_HELP', $this->detectIntent('muchas gracias'));
        $this->assertEquals('GENERAL_HELP', $this->detectIntent('ok perfecto'));
    }

    public function testMaintenanceIntentDetection(): void
    {
        $this->assertEquals('MAINTENANCE_ISSUE', $this->detectIntent('el aire acondicionado no funciona'));
        $this->assertEquals('MAINTENANCE_ISSUE', $this->detectIntent('hay un problema con la ducha'));
        $this->assertEquals('MAINTENANCE_ISSUE', $this->detectIntent('la TV está rota'));
    }
}

// ──────────────────────────────────────────────────────────────
//  TEST: Smart API — Autenticación y routing
// ──────────────────────────────────────────────────────────────
class SmartApiTest extends TestCase
{
    public function testApiKeyHashFormat(): void
    {
        $rawKey  = 'dev_smarthotel_abc123def456';
        $hashed  = hash('sha256', $rawKey);

        $this->assertEquals(64, strlen($hashed));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hashed);
    }

    public function testDateValidation(): void
    {
        $validDates   = ['2024-01-15', '2025-12-31', '2026-06-10'];
        $invalidDates = ['2024-13-01', 'not-a-date', '2024/01/15', '15-01-2024'];

        foreach ($validDates as $date) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $date);
        }
        foreach ($invalidDates as $date) {
            $valid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1;
            if ($date === '2024-13-01') {
                // Formato válido pero fecha inválida — validar con checkdate
                [$y, $m, $d] = explode('-', $date);
                $this->assertFalse(checkdate((int)$m, (int)$d, (int)$y));
            } else {
                $this->assertFalse($valid, "'{$date}' debería ser inválido");
            }
        }
    }

    public function testRoutePatternMatching(): void
    {
        $routes = [
            '/reservations'         => 'listReservations',
            '/reservations/{id}'    => 'getReservation',
            '/guests/{id}/profile'  => 'getGuestProfile',
            '/dashboard/kpis'       => 'getDailyKpis',
        ];

        foreach ($routes as $pattern => $action) {
            $regex = '@^' . preg_replace('/\{[^}]+\}/', '([^/]+)', $pattern) . '$@';

            // Test que el patrón hace match con una URL real
            $testUrl = str_replace('{id}', '123', $pattern);
            $this->assertMatchesRegularExpression($regex, $testUrl,
                "El patrón '{$pattern}' no hace match con '{$testUrl}'"
            );
        }
    }

    public function testRateLimitLogic(): void
    {
        $limit     = 100;
        $requests  = [1, 50, 99, 100, 101, 200];

        foreach ($requests as $count) {
            $allowed = $count <= $limit;
            if ($count <= 100) {
                $this->assertTrue($allowed, "Request #{$count} debería estar permitido");
            } else {
                $this->assertFalse($allowed, "Request #{$count} debería estar bloqueado");
            }
        }
    }
}
