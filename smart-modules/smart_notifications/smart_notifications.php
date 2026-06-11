<?php
/**
 * SmartHotelOS — Módulo Smart Notifications
 * Grupo Smart de Administración
 *
 * Hub central de notificaciones: WhatsApp Business API, email,
 * push notifications para la app del staff y alertas internas.
 * Todos los módulos disparan notificaciones a través de este hub.
 *
 * @module  smart_notifications
 * @version 1.0.0
 */

if (!defined('_PS_VERSION_')) { exit; }

class Smart_Notifications extends Module
{
    const MODULE_VERSION = '1.0.0';

    const CHANNELS = ['WHATSAPP', 'EMAIL', 'PUSH', 'INTERNAL', 'SMS'];

    /** Templates de mensajes disponibles por canal */
    const TEMPLATES = [
        // WhatsApp (deben estar aprobados en Meta)
        'welcome_checkin'          => ['whatsapp', 'email'],
        'pre_checkin_reminder'     => ['whatsapp', 'email'],
        'checkout_reminder'        => ['whatsapp'],
        'checkout_confirmation'    => ['whatsapp', 'email'],
        'spa_booking_confirmed'    => ['whatsapp', 'email'],
        'spa_booking_reminder'     => ['whatsapp'],
        'room_ready'               => ['whatsapp'],
        'maintenance_urgent'       => ['email', 'push', 'internal'],
        'maintenance_sla_breach'   => ['email', 'push', 'internal'],
        'housekeeping_urgent'      => ['push', 'internal'],
        'low_occupancy_alert'      => ['email', 'internal'],
        'overbooking_risk'         => ['email', 'push', 'internal'],
        'guest_request'            => ['push', 'internal'],
        'review_request'           => ['whatsapp', 'email'],
        'birthday_greeting'        => ['whatsapp'],
    ];

    public function __construct()
    {
        $this->name      = 'smart_notifications';
        $this->tab       = 'administration';
        $this->version   = self::MODULE_VERSION;
        $this->author    = 'Smart Hotel Group';
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('Smart Notifications Hub');
        $this->description = $this->l(
            'Hub centralizado de notificaciones: WhatsApp Business API, email, ' .
            'push para staff y alertas internas. Todas las comunicaciones en un solo lugar.'
        );
    }

    public function install(): bool
    {
        return parent::install()
            && $this->installSql()
            && $this->installTab()
            && $this->registerHook(['actionSmartSendNotification', 'actionSmartSendWhatsApp'])
            && $this->installDefaultConfig();
    }

    public function uninstall(): bool
    {
        return parent::uninstall() && $this->uninstallSql() && $this->uninstallTab();
    }

    private function installSql(): bool
    {
        $queries = [
            // Cola de notificaciones
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_notification_queue` (
                `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `id_property`   INT UNSIGNED NOT NULL DEFAULT 1,
                `channel`       ENUM("WHATSAPP","EMAIL","PUSH","INTERNAL","SMS") NOT NULL,
                `template`      VARCHAR(100) NOT NULL,
                `recipient`     VARCHAR(200) NOT NULL COMMENT "Teléfono, email, employee_id",
                `data`          JSON COMMENT "Variables del template",
                `status`        ENUM("pending","sending","sent","failed","skipped") DEFAULT "pending",
                `attempts`      TINYINT UNSIGNED DEFAULT 0,
                `error_msg`     TEXT NULL,
                `scheduled_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
                `sent_at`       DATETIME NULL,
                `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_status_scheduled` (`status`, `scheduled_at`),
                INDEX `idx_channel`          (`channel`, `status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            // Log de notificaciones enviadas
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_notification_log` (
                `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `id_queue`      INT UNSIGNED NULL,
                `channel`       VARCHAR(20),
                `template`      VARCHAR(100),
                `recipient`     VARCHAR(200),
                `status`        VARCHAR(20),
                `provider_ref`  VARCHAR(200) COMMENT "ID de la plataforma (WhatsApp message_id, etc.)",
                `sent_at`       DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_sent`    (`sent_at`),
                INDEX `idx_channel` (`channel`, `sent_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        ];
        foreach ($queries as $sql) {
            if (!Db::getInstance()->execute($sql)) { return false; }
        }
        return true;
    }

    private function installTab(): bool
    {
        $tab = new Tab();
        $tab->active     = 1;
        $tab->class_name = 'AdminSmartNotifications';
        $tab->module     = $this->name;
        $tab->id_parent  = (int) Tab::getIdFromClassName('AdminSmartHotel');
        foreach (Language::getLanguages() as $l) { $tab->name[$l['id_lang']] = 'Notificaciones'; }
        return (bool) $tab->add();
    }

    private function installDefaultConfig(): bool
    {
        foreach ([
            'SMART_NOTIF_WHATSAPP_ENABLED'  => 0,
            'SMART_NOTIF_WHATSAPP_API_URL'  => 'https://graph.facebook.com/v18.0',
            'SMART_NOTIF_WHATSAPP_PHONE_ID' => '',
            'SMART_NOTIF_WHATSAPP_TOKEN'    => '',
            'SMART_NOTIF_EMAIL_ENABLED'     => 1,
            'SMART_NOTIF_PUSH_ENABLED'      => 0,
            'SMART_NOTIF_BATCH_SIZE'        => 50,  // notificaciones por ejecución del cron
        ] as $k => $v) {
            Configuration::updateValue($k, $v);
        }
        return true;
    }

    private function uninstallSql(): bool
    {
        foreach (['smart_notification_queue','smart_notification_log'] as $t) {
            Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . pSQL($t) . '`');
        }
        return true;
    }

    private function uninstallTab(): bool
    {
        $id = (int) Tab::getIdFromClassName('AdminSmartNotifications');
        if ($id) { (new Tab($id))->delete(); }
        return true;
    }

    // ──────────────────────────────────────────────────────────
    //  HOOK — Punto de entrada para todos los módulos
    // ──────────────────────────────────────────────────────────

    /**
     * Recibe notificaciones de todos los módulos del sistema.
     * Hook: actionSmartSendNotification
     */
    public function hookActionSmartSendNotification(array $params): void
    {
        $type      = $params['type'] ?? '';
        $channels  = self::TEMPLATES[$type] ?? [];
        $recipient = $params['recipient'] ?? null;
        $data      = $params['data'] ?? $params;

        foreach ($channels as $channel) {
            $channel = strtoupper($channel);
            switch ($channel) {
                case 'WHATSAPP':
                    if ($recipient && Configuration::get('SMART_NOTIF_WHATSAPP_ENABLED')) {
                        $this->queueNotification('WHATSAPP', $type, $recipient, $data);
                    }
                    break;
                case 'EMAIL':
                    if ($recipient && Configuration::get('SMART_NOTIF_EMAIL_ENABLED')) {
                        $this->queueNotification('EMAIL', $type, $recipient, $data);
                    }
                    break;
                case 'INTERNAL':
                    $this->queueNotification('INTERNAL', $type, 'all_managers', $data);
                    break;
                case 'PUSH':
                    $this->queueNotification('PUSH', $type, $recipient ?? 'all_staff', $data);
                    break;
            }
        }
    }

    /**
     * Hook específico para WhatsApp (legado, redirige al hub).
     */
    public function hookActionSmartSendWhatsApp(array $params): void
    {
        $this->hookActionSmartSendNotification(array_merge($params, ['type' => $params['template'] ?? '']));
    }

    // ──────────────────────────────────────────────────────────
    //  ENVÍO REAL
    // ──────────────────────────────────────────────────────────

    /**
     * Encolar notificación.
     */
    public function queueNotification(
        string $channel, string $template, string $recipient,
        array $data = [], ?string $scheduledAt = null
    ): int {
        Db::getInstance()->insert('smart_notification_queue', [
            'channel'      => pSQL($channel),
            'template'     => pSQL($template),
            'recipient'    => pSQL($recipient),
            'data'         => pSQL(json_encode($data)),
            'status'       => 'pending',
            'scheduled_at' => pSQL($scheduledAt ?? date('Y-m-d H:i:s')),
        ]);
        return (int) Db::getInstance()->Insert_ID();
    }

    /**
     * Procesar la cola de notificaciones.
     * Llamar desde cron cada minuto.
     */
    public function processQueue(): array
    {
        $batchSize = (int) Configuration::get('SMART_NOTIF_BATCH_SIZE') ?: 50;

        $pending = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'smart_notification_queue`
             WHERE status = "pending" AND scheduled_at <= NOW()
             AND attempts < 3
             ORDER BY scheduled_at ASC
             LIMIT ' . $batchSize
        ) ?: [];

        $results = ['sent' => 0, 'failed' => 0, 'skipped' => 0];

        foreach ($pending as $notif) {
            // Marcar como enviando
            Db::getInstance()->update('smart_notification_queue',
                ['status' => 'sending', 'attempts' => (int) $notif['attempts'] + 1],
                'id = ' . (int) $notif['id']
            );

            $data = json_decode($notif['data'], true) ?: [];
            $sent = false;
            $error = '';

            try {
                switch ($notif['channel']) {
                    case 'WHATSAPP':
                        $sent = $this->sendWhatsApp($notif['recipient'], $notif['template'], $data);
                        break;
                    case 'EMAIL':
                        $sent = $this->sendEmail($notif['recipient'], $notif['template'], $data);
                        break;
                    case 'INTERNAL':
                        $sent = $this->sendInternal($notif['template'], $data);
                        break;
                    case 'PUSH':
                        $sent = true; // TODO: FCM / APNs
                        break;
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
            }

            $newStatus = $sent ? 'sent' : 'failed';
            Db::getInstance()->update('smart_notification_queue', [
                'status'    => pSQL($newStatus),
                'error_msg' => pSQL($error),
                'sent_at'   => $sent ? date('Y-m-d H:i:s') : null,
            ], 'id = ' . (int) $notif['id']);

            $sent ? $results['sent']++ : $results['failed']++;

            // Log
            Db::getInstance()->insert('smart_notification_log', [
                'id_queue'  => (int) $notif['id'],
                'channel'   => pSQL($notif['channel']),
                'template'  => pSQL($notif['template']),
                'recipient' => pSQL($notif['recipient']),
                'status'    => pSQL($newStatus),
            ]);
        }

        return $results;
    }

    /**
     * Enviar mensaje de WhatsApp via Meta Cloud API.
     */
    private function sendWhatsApp(string $phone, string $template, array $data): bool
    {
        $apiUrl   = Configuration::get('SMART_NOTIF_WHATSAPP_API_URL');
        $phoneId  = Configuration::get('SMART_NOTIF_WHATSAPP_PHONE_ID');
        $token    = Configuration::get('SMART_NOTIF_WHATSAPP_TOKEN');

        if (!$phoneId || !$token) { return false; }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => preg_replace('/[^0-9]/', '', $phone),
            'type'              => 'template',
            'template'          => [
                'name'     => $template,
                'language' => ['code' => 'es'],
                'components' => $this->buildWhatsAppComponents($template, $data),
            ],
        ];

        $ch = curl_init("{$apiUrl}/{$phoneId}/messages");
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ],
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            return isset($result['messages'][0]['id']);
        }

        return false;
    }

    /**
     * Enviar email usando el sistema de PrestaShop/QloApps.
     */
    private function sendEmail(string $to, string $template, array $data): bool
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { return false; }

        return Mail::send(
            (int) Configuration::get('PS_LANG_DEFAULT'),
            'smart_' . $template,
            $data['subject'] ?? 'Notificación — Smart Hotel',
            $data,
            $to,
            $data['recipient_name'] ?? null
        );
    }

    /**
     * Notificación interna (se guarda en dashboard como alerta).
     */
    private function sendInternal(string $type, array $data): bool
    {
        $dash = Module::getInstanceByName('smart_dashboard');
        if (!$dash) { return false; }

        $severity = in_array($type, ['maintenance_urgent','overbooking_risk','maintenance_sla_breach'])
            ? 'critical' : 'warning';

        $dash->createAlert(
            (int) ($data['id_property'] ?? 1),
            strtoupper($type),
            $severity,
            $data['title'] ?? $type,
            $data['message'] ?? '',
            $data
        );

        return true;
    }

    private function buildWhatsAppComponents(string $template, array $data): array
    {
        // Mapa de variables por template
        $componentMap = [
            'welcome_checkin'    => [['type' => 'text', 'text' => $data['guest_name'] ?? '']],
            'pre_checkin_reminder' => [
                ['type' => 'text', 'text' => $data['guest_name'] ?? ''],
                ['type' => 'text', 'text' => $data['checkin_date'] ?? ''],
                ['type' => 'text', 'text' => $data['booking_ref'] ?? ''],
            ],
            'spa_booking_confirmed' => [
                ['type' => 'text', 'text' => $data['service_name'] ?? ''],
                ['type' => 'text', 'text' => $data['date'] ?? ''],
                ['type' => 'text', 'text' => $data['time'] ?? ''],
            ],
            'review_request' => [
                ['type' => 'text', 'text' => $data['guest_name'] ?? ''],
                ['type' => 'text', 'text' => $data['hotel_name'] ?? Configuration::get('PS_SHOP_NAME')],
            ],
        ];

        $params = $componentMap[$template] ?? [];
        if (empty($params)) { return []; }

        return [['type' => 'body', 'parameters' => $params]];
    }

    public function getContent(): string { return $this->display(__FILE__, 'views/templates/admin/configuration.tpl'); }
}
