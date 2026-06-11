#!/usr/bin/env php
<?php
/**
 * SmartHotelOS — Gestor de Tareas Programadas (Cron)
 * Grupo Smart de Administración
 *
 * Ejecuta las tareas automáticas del sistema hotelero.
 * Configurar en crontab del servidor:
 *
 *   # Cada minuto — notificaciones y alertas
 *   * * * * * php /var/www/html/scripts/cron.php --task=minute
 *
 *   # Cada hora — yield manager y check SLA
 *   0 * * * * php /var/www/html/scripts/cron.php --task=hourly
 *
 *   # A las 23:59 — snapshot diario de KPIs
 *   59 23 * * * php /var/www/html/scripts/cron.php --task=daily_snapshot
 *
 *   # A las 06:00 — plan de housekeeping del día
 *   0 6 * * * php /var/www/html/scripts/cron.php --task=hk_plan
 *
 *   # A las 07:00 — recordatorios de spa
 *   0 7 * * * php /var/www/html/scripts/cron.php --task=spa_reminders
 *
 *   # A las 08:00 — recordatorios de pre check-in
 *   0 8 * * * php /var/www/html/scripts/cron.php --task=precheckin
 *
 *   # A las 22:00 — turndown service
 *   0 22 * * * php /var/www/html/scripts/cron.php --task=turndown
 *
 * @version 1.0.0
 */

// ── Bootstrap de QloApps ──────────────────────────────────────
define('_PS_ROOT_DIR_', dirname(__DIR__) . '/core');

if (!file_exists(_PS_ROOT_DIR_ . '/config/config.inc.php')) {
    echo "[CRON ERROR] config.inc.php no encontrado en " . _PS_ROOT_DIR_ . "\n";
    exit(1);
}

require_once _PS_ROOT_DIR_ . '/config/config.inc.php';
require_once _PS_ROOT_DIR_ . '/init.php';

// ── Parsear argumentos ────────────────────────────────────────
$opts         = getopt('', ['task:', 'property:', 'dry-run', 'verbose']);
$task         = $opts['task']     ?? 'all';
$idProperty   = (int) ($opts['property'] ?? Configuration::get('DEFAULT_PROPERTY_ID') ?: 1);
$dryRun       = isset($opts['dry-run']);
$verbose      = isset($opts['verbose']);

$start = microtime(true);

function log_cron(string $msg, string $level = 'info'): void {
    $prefix = match($level) {
        'ok'    => '✓',
        'error' => '✗',
        'warn'  => '⚠',
        default => '→',
    };
    echo '[' . date('Y-m-d H:i:s') . '] [' . strtoupper($level) . '] ' . $prefix . ' ' . $msg . PHP_EOL;
}

log_cron("SmartHotelOS Cron — Task: {$task} | Property: {$idProperty}" . ($dryRun ? ' [DRY-RUN]' : ''));

// ── Dispatcher de tareas ──────────────────────────────────────

switch ($task) {

    // ── CADA MINUTO ──────────────────────────────────────────
    case 'minute':
        // 1. Procesar cola de notificaciones
        $notif = Module::getInstanceByName('smart_notifications');
        if ($notif) {
            $result = $dryRun ? ['sent' => 0, 'failed' => 0] : $notif->processQueue();
            log_cron("Notificaciones: {$result['sent']} enviadas, {$result['failed']} fallidas", 'ok');
        }

        // 2. Evaluar alertas del dashboard
        $dash = Module::getInstanceByName('smart_dashboard');
        if ($dash) {
            if (!$dryRun) $dash->evaluateAlerts($idProperty);
            log_cron("Alertas del dashboard evaluadas", 'ok');
        }
        break;

    // ── CADA HORA ────────────────────────────────────────────
    case 'hourly':
        // 1. Verificar SLA de mantenimiento
        $maint = Module::getInstanceByName('smart_maintenance');
        if ($maint) {
            $breached = $dryRun ? 0 : $maint->checkSlaBreaches();
            if ($breached > 0) {
                log_cron("{$breached} SLA(s) de mantenimiento incumplidos", 'warn');
            } else {
                log_cron("SLA de mantenimiento: todos OK", 'ok');
            }
        }

        // 2. Yield Manager (si auto_apply está activo)
        $rate = Module::getInstanceByName('smart_ratemanager');
        if ($rate) {
            // Calcular para los próximos 7 días
            for ($i = 1; $i <= 7; $i++) {
                $targetDate = date('Y-m-d', strtotime("+{$i} days"));
                $result = $dryRun
                    ? ['active' => false]
                    : $rate->runYieldEngine($idProperty, $targetDate);

                if ($verbose && $result['active'] && !empty($result['suggestions'])) {
                    log_cron("Yield {$targetDate}: {$result['multiplier']}x — {$result['reason']}", 'ok');
                }
            }
            log_cron("Yield Manager ejecutado para próximos 7 días", 'ok');
        }

        // 3. Verificar habitaciones con tareas HK demasiado largas
        $hkOverdue = Db::getInstance()->executeS(
            'SELECT t.id, t.id_room, t.started_at,
                    TIMESTAMPDIFF(MINUTE, t.started_at, NOW()) as minutes_elapsed
             FROM `' . _DB_PREFIX_ . 'smart_hk_task` t
             WHERE t.status = "IN_PROGRESS"
             AND TIMESTAMPDIFF(MINUTE, t.started_at, NOW()) > 90
             LIMIT 10'
        );
        if (!empty($hkOverdue)) {
            log_cron(count($hkOverdue) . " tarea(s) HK con más de 90 minutos sin completar", 'warn');
            if (!$dryRun) {
                foreach ($hkOverdue as $task) {
                    Hook::exec('actionSmartSendNotification', [
                        'type'    => 'housekeeping_urgent',
                        'title'   => 'Tarea HK prolongada — Hab. ' . $task['id_room'],
                        'message' => 'Lleva ' . $task['minutes_elapsed'] . ' minutos en progreso.',
                    ]);
                }
            }
        }
        break;

    // ── SNAPSHOT DIARIO KPIs ─────────────────────────────────
    case 'daily_snapshot':
        $dash = Module::getInstanceByName('smart_dashboard');
        if ($dash) {
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $ok = $dryRun ? true : $dash->saveSnapshot($idProperty, $yesterday);
            if ($ok) {
                log_cron("Snapshot guardado para {$yesterday}", 'ok');
            } else {
                log_cron("Error guardando snapshot para {$yesterday}", 'error');
                exit(1);
            }
        }

        // Purgar logs de API con más de 30 días
        if (!$dryRun) {
            Db::getInstance()->execute(
                'DELETE FROM `' . _DB_PREFIX_ . 'smart_api_log`
                 WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)'
            );
            Db::getInstance()->execute(
                'DELETE FROM `' . _DB_PREFIX_ . 'smart_notification_log`
                 WHERE sent_at < DATE_SUB(NOW(), INTERVAL 90 DAY)'
            );
        }
        log_cron("Logs de API y notificaciones purgados", 'ok');
        break;

    // ── PLAN HK DEL DÍA ─────────────────────────────────────
    case 'hk_plan':
        $hk = Module::getInstanceByName('smart_housekeeping');
        if ($hk) {
            $today  = date('Y-m-d');
            $result = $dryRun
                ? ['success' => true, 'tasks_created' => 0, 'staff_assigned' => 0]
                : $hk->generateDailyPlan($today, $idProperty);

            if ($result['success']) {
                log_cron(
                    "Plan HK: {$result['tasks_created']} tareas · " .
                    "{$result['staff_assigned']} personas · " .
                    "{$result['rooms_pending']} hab. sin asignar",
                    'ok'
                );
            } else {
                log_cron("Error generando plan HK: " . ($result['message'] ?? 'Error desconocido'), 'error');
            }
        }
        break;

    // ── RECORDATORIOS SPA ────────────────────────────────────
    case 'spa_reminders':
        $remindHours = (int) Configuration::get('SMART_SPA_REMINDER_HOURS') ?: 2;
        $upcoming = Db::getInstance()->executeS(
            "SELECT sb.*, ss.name as service_name,
                    c.firstname, c.lastname, c.phone, c.phone_mobile
             FROM `{$_DB_PREFIX_}smart_spa_booking` sb
             JOIN `{$_DB_PREFIX_}smart_spa_service` ss ON ss.id = sb.id_service
             JOIN `{$_DB_PREFIX_}customer` c ON c.id_customer = sb.id_customer
             WHERE sb.status = 'CONFIRMED'
             AND sb.appointment_date = CURDATE()
             AND TIME(sb.start_time) BETWEEN TIME(NOW()) AND TIME(ADDTIME(NOW(), '{$remindHours}:00:00'))"
        ) ?? [];

        foreach ($upcoming as $booking) {
            $phone = $booking['phone_mobile'] ?: $booking['phone'];
            if ($phone && !$dryRun) {
                Hook::exec('actionSmartSendNotification', [
                    'type'         => 'spa_booking_reminder',
                    'recipient'    => $phone,
                    'data'         => [
                        'service_name' => $booking['service_name'],
                        'date'         => $booking['appointment_date'],
                        'time'         => $booking['start_time'],
                        'guest_name'   => $booking['firstname'],
                    ],
                ]);
            }
            if ($verbose) {
                log_cron("Recordatorio spa → {$booking['firstname']} {$booking['lastname']} — {$booking['service_name']} a las {$booking['start_time']}");
            }
        }
        log_cron("Recordatorios de spa: " . count($upcoming) . " enviados", 'ok');
        break;

    // ── PRE CHECK-IN ─────────────────────────────────────────
    case 'precheckin':
        $preHours = (int) Configuration::get('SMART_FD_PRE_CHECKIN_HOURS') ?: 24;
        $tomorrow = date('Y-m-d', strtotime("+{$preHours} hours"));

        $arrivals = Db::getInstance()->executeS(
            "SELECT b.id, c.firstname, c.lastname, c.phone, c.phone_mobile,
                    b.booking_date_from, b.booking_date_to
             FROM `" . _DB_PREFIX_ . "htl_booking_detail` b
             JOIN `" . _DB_PREFIX_ . "customer` c ON c.id_customer = b.id_customer
             WHERE DATE(b.booking_date_from) = '{$tomorrow}'
             AND b.booking_status = 1"
        ) ?? [];

        foreach ($arrivals as $arrival) {
            $phone = $arrival['phone_mobile'] ?: $arrival['phone'];
            if ($phone && !$dryRun) {
                Hook::exec('actionSmartSendNotification', [
                    'type'      => 'pre_checkin_reminder',
                    'recipient' => $phone,
                    'data'      => [
                        'guest_name'    => $arrival['firstname'],
                        'checkin_date'  => date('d/m/Y', strtotime($arrival['booking_date_from'])),
                        'booking_ref'   => 'REF-' . str_pad($arrival['id'], 6, '0', STR_PAD_LEFT),
                    ],
                ]);
            }
        }
        log_cron("Pre-checkin: " . count($arrivals) . " notificaciones enviadas", 'ok');
        break;

    // ── TURNDOWN SERVICE ─────────────────────────────────────
    case 'turndown':
        if ((int) Configuration::get('SMART_HK_TURNDOWN_ENABLED')) {
            $hk = Module::getInstanceByName('smart_housekeeping');
            if ($hk) {
                // Crear tareas de turndown para habitaciones ocupadas
                $occupied = Db::getInstance()->executeS(
                    'SELECT ra.id_room, ra.id_booking
                     FROM `' . _DB_PREFIX_ . 'smart_room_assignment` ra
                     JOIN `' . _DB_PREFIX_ . 'htl_booking_detail` b ON b.id = ra.id_booking
                     WHERE ra.active = 1 AND b.booking_status IN (2,6)
                     AND ra.id_room NOT IN (
                         SELECT id_room FROM `' . _DB_PREFIX_ . 'smart_hk_task`
                         WHERE task_type = "TURNDOWN"
                         AND DATE(scheduled_for) = CURDATE()
                     )'
                ) ?? [];

                $created = 0;
                foreach ($occupied as $room) {
                    if (!$dryRun) {
                        $hk->createTask([
                            'id_room'    => $room['id_room'],
                            'id_booking' => $room['id_booking'],
                            'task_type'  => 'TURNDOWN',
                            'priority'   => 2,
                            'notes'      => 'Servicio de cobertura nocturna',
                            'scheduled_for' => date('Y-m-d'),
                        ]);
                    }
                    $created++;
                }
                log_cron("Turndown: {$created} tareas creadas", 'ok');
            }
        } else {
            log_cron("Turndown service desactivado en config", 'warn');
        }
        break;

    // ── TODAS LAS TAREAS (cron completo) ─────────────────────
    case 'all':
        $allTasks = ['minute', 'hourly', 'daily_snapshot', 'hk_plan', 'spa_reminders', 'precheckin'];
        $hour     = (int) date('H');
        $minute   = (int) date('i');

        // Ejecutar según la hora actual
        passthru("php {$_SERVER['argv'][0]} --task=minute --property={$idProperty}" . ($dryRun ? ' --dry-run' : ''));

        if ($minute === 0) {
            passthru("php {$_SERVER['argv'][0]} --task=hourly --property={$idProperty}" . ($dryRun ? ' --dry-run' : ''));
        }
        if ($hour === 6  && $minute === 0) passthru("php {$_SERVER['argv'][0]} --task=hk_plan --property={$idProperty}");
        if ($hour === 7  && $minute === 0) passthru("php {$_SERVER['argv'][0]} --task=spa_reminders --property={$idProperty}");
        if ($hour === 8  && $minute === 0) passthru("php {$_SERVER['argv'][0]} --task=precheckin --property={$idProperty}");
        if ($hour === 22 && $minute === 0) passthru("php {$_SERVER['argv'][0]} --task=turndown --property={$idProperty}");
        if ($hour === 23 && $minute === 59)passthru("php {$_SERVER['argv'][0]} --task=daily_snapshot --property={$idProperty}");
        break;

    default:
        log_cron("Tarea desconocida: '{$task}'", 'error');
        echo "\nTareas disponibles:\n";
        echo "  minute, hourly, daily_snapshot, hk_plan, spa_reminders, precheckin, turndown, all\n\n";
        exit(1);
}

$elapsed = round(microtime(true) - $start, 3);
log_cron("Completado en {$elapsed}s", 'ok');
