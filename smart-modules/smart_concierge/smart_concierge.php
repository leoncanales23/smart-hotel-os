<?php
/**
 * SmartHotelOS — Módulo Smart Concierge
 * Grupo Smart de Administración
 *
 * Asistente digital de conserjería 24/7 vía WhatsApp Business API.
 * Gestiona solicitudes de huéspedes, reservas de actividades,
 * transfers, y peticiones especiales sin intervención humana.
 * Escalada automática al staff cuando el bot no puede resolver.
 *
 * @module  smart_concierge
 * @version 1.0.0
 */

if (!defined('_PS_VERSION_')) { exit; }

class Smart_Concierge extends Module
{
    const MODULE_VERSION = '1.0.0';

    /** Intenciones que el bot puede detectar */
    const INTENTS = [
        'CHECKIN_QUESTION'    => 'Pregunta sobre check-in',
        'CHECKOUT_REQUEST'    => 'Solicitud de check-out',
        'ROOM_SERVICE'        => 'Servicio a la habitación',
        'TRANSPORT'           => 'Transporte / Transfer',
        'RESTAURANT_RES'      => 'Reserva de restaurante',
        'SPA_BOOKING'         => 'Reserva de spa',
        'AMENITIES_REQUEST'   => 'Solicitud de amenidades',
        'MAINTENANCE_ISSUE'   => 'Problema en la habitación',
        'TOURIST_INFO'        => 'Información turística',
        'WIFI_INFO'           => 'Datos de WiFi',
        'CHECKOUT_TIME'       => 'Pregunta hora de check-out',
        'LATE_CHECKOUT'       => 'Solicitud late check-out',
        'EXTRA_TOWELS'        => 'Toallas / blancos extra',
        'WAKE_UP_CALL'        => 'Servicio despertador',
        'GENERAL_HELP'        => 'Ayuda general',
        'ESCALATE'            => 'Escalar a humano',
    ];

    /** Respuestas automáticas multi-idioma */
    const AUTO_RESPONSES = [
        'WIFI_INFO' => [
            'es' => "📶 *WiFi del Hotel Smart*\n\nRed: SmartHotel_Guest\nContraseña: smart{ROOM_NUM}2024\n\nSi tiene algún problema de conexión, comuníquese con recepción.",
            'en' => "📶 *Smart Hotel WiFi*\n\nNetwork: SmartHotel_Guest\nPassword: smart{ROOM_NUM}2024\n\nFor connection issues, please contact reception.",
        ],
        'CHECKOUT_TIME' => [
            'es' => "🕐 La hora de check-out es a las *{CHECKOUT_TIME}*.\n\n¿Necesita solicitar un late check-out? Le puedo verificar disponibilidad.",
            'en' => "🕐 Check-out time is at *{CHECKOUT_TIME}*.\n\nWould you like to request a late check-out? I can check availability for you.",
        ],
        'GENERAL_HELP' => [
            'es' => "🏨 *¡Bienvenido al Concierge Digital del Hotel Smart!*\n\nEstoy aquí para ayudarle. Puedo asistirle con:\n\n• 🍽 Reservas en el restaurante\n• 💆 Reservas en el spa\n• 🚗 Transporte y transfers\n• 🛎 Servicio a la habitación\n• 📍 Información turística\n• 🔧 Reportar problemas en su habitación\n\n¿En qué le puedo ayudar?",
        ],
        'ESCALATE' => [
            'es' => "Entendido. Le estoy conectando con un miembro de nuestro equipo de recepción. Por favor espere un momento. ⌛",
        ],
    ];

    public function __construct()
    {
        $this->name      = 'smart_concierge';
        $this->tab       = 'administration';
        $this->version   = self::MODULE_VERSION;
        $this->author    = 'Smart Hotel Group';
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('Smart Concierge Digital');
        $this->description = $this->l(
            'Bot de conserjería 24/7 por WhatsApp: solicitudes de huéspedes, ' .
            'reservas, transfers, información y escalada inteligente al staff.'
        );
    }

    public function install(): bool
    {
        return parent::install()
            && $this->installSql()
            && $this->installTabs()
            && $this->registerHook([
                'actionSmartCheckIn',
                'actionSmartCheckOut',
                'displaySmartDashboardWidgets',
            ])
            && $this->installDefaultConfig();
    }

    public function uninstall(): bool
    {
        return parent::uninstall() && $this->uninstallSql() && $this->uninstallTabs();
    }

    private function installSql(): bool
    {
        $queries = [

            // Conversaciones del bot
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_concierge_conversation` (
                `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `id_property`     INT UNSIGNED NOT NULL DEFAULT 1,
                `wa_phone`        VARCHAR(20) NOT NULL COMMENT "Número WhatsApp del huésped",
                `id_customer`     INT UNSIGNED NULL,
                `id_booking`      INT UNSIGNED NULL,
                `room_number`     VARCHAR(10) NULL,
                `language`        VARCHAR(5) DEFAULT "es",
                `status`          ENUM("bot","escalated","closed") DEFAULT "bot",
                `id_agent`        INT UNSIGNED NULL COMMENT "Agente asignado si escaló",
                `context`         JSON COMMENT "Estado actual del flujo de conversación",
                `last_message_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uk_phone_booking` (`wa_phone`, `id_booking`),
                INDEX `idx_status`  (`status`),
                INDEX `idx_phone`   (`wa_phone`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            -- Mensajes de la conversación
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_concierge_message` (
                `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `id_conversation` INT UNSIGNED NOT NULL,
                `direction`       ENUM("inbound","outbound") NOT NULL,
                `message_type`    ENUM("text","image","audio","document","template") DEFAULT "text",
                `content`         TEXT NOT NULL,
                `wa_message_id`   VARCHAR(100) NULL COMMENT "ID del mensaje en WhatsApp",
                `intent`          VARCHAR(50) NULL COMMENT "Intención detectada",
                `confidence`      DECIMAL(4,3) NULL,
                `handled_by`      ENUM("bot","agent") DEFAULT "bot",
                `id_agent`        INT UNSIGNED NULL,
                `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_conversation` (`id_conversation`),
                INDEX `idx_created`      (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            -- Solicitudes gestionadas por el concierge
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_concierge_request` (
                `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `id_conversation` INT UNSIGNED NOT NULL,
                `id_property`     INT UNSIGNED NOT NULL DEFAULT 1,
                `id_booking`      INT UNSIGNED NULL,
                `request_type`    VARCHAR(50) NOT NULL,
                `description`     TEXT,
                `status`          ENUM("open","in_progress","completed","cancelled") DEFAULT "open",
                `id_assigned_to`  INT UNSIGNED NULL,
                `resolution`      TEXT,
                `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
                `resolved_at`     DATETIME NULL,
                INDEX `idx_booking` (`id_booking`),
                INDEX `idx_status`  (`status`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        ];

        foreach ($queries as $sql) {
            if (!Db::getInstance()->execute($sql)) { return false; }
        }
        return true;
    }

    private function installTabs(): bool
    {
        foreach ([
            ['AdminSmartConcierge',         'Concierge Digital',      'AdminSmartHotel'],
            ['AdminSmartConciergeChats',     'Conversaciones',         'AdminSmartConcierge'],
            ['AdminSmartConciergeRequests',  'Solicitudes',            'AdminSmartConcierge'],
            ['AdminSmartConciergeSettings',  'Configuración Bot',      'AdminSmartConcierge'],
        ] as [$cls, $name, $parent]) {
            $tab = new Tab();
            $tab->active     = 1;
            $tab->class_name = $cls;
            $tab->module     = $this->name;
            $tab->id_parent  = (int) Tab::getIdFromClassName($parent);
            foreach (Language::getLanguages() as $l) { $tab->name[$l['id_lang']] = $name; }
            $tab->add();
        }
        return true;
    }

    private function installDefaultConfig(): bool
    {
        foreach ([
            'SMART_CC_BOT_ENABLED'         => 0,
            'SMART_CC_BOT_NAME'            => 'Sofia',
            'SMART_CC_ESCALATE_KEYWORDS'   => 'humano,persona,recepción,ayuda,emergencia',
            'SMART_CC_ESCALATE_AFTER_MSGS' => 5,
            'SMART_CC_BUSINESS_HOURS'      => '07:00-23:00',
            'SMART_CC_WEBHOOK_VERIFY_TOKEN'=> 'smart_hotel_webhook_' . substr(md5(uniqid()), 0, 12),
        ] as $k => $v) {
            Configuration::updateValue($k, $v);
        }
        return true;
    }

    private function uninstallSql(): bool
    {
        foreach (['smart_concierge_conversation','smart_concierge_message','smart_concierge_request'] as $t) {
            Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . pSQL($t) . '`');
        }
        return true;
    }

    private function uninstallTabs(): bool
    {
        foreach (['AdminSmartConcierge','AdminSmartConciergeChats',
                  'AdminSmartConciergeRequests','AdminSmartConciergeSettings'] as $c) {
            $id = (int) Tab::getIdFromClassName($c);
            if ($id) { (new Tab($id))->delete(); }
        }
        return true;
    }

    // ──────────────────────────────────────────────────────────
    //  WEBHOOK — Recibir mensajes de WhatsApp
    // ──────────────────────────────────────────────────────────

    /**
     * Punto de entrada del webhook de Meta/WhatsApp.
     * Se llama desde FrontController dedicado.
     */
    public function handleWebhook(array $payload): void
    {
        if (!isset($payload['entry'])) { return; }

        foreach ($payload['entry'] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                foreach ($value['messages'] ?? [] as $message) {
                    $this->processInboundMessage($message, $value['contacts'][0] ?? []);
                }
                // Actualizar estados de mensajes enviados
                foreach ($value['statuses'] ?? [] as $status) {
                    $this->updateMessageStatus($status);
                }
            }
        }
    }

    /**
     * Procesar un mensaje entrante del huésped.
     */
    private function processInboundMessage(array $message, array $contact): void
    {
        $phone     = $message['from'];
        $text      = $message['text']['body'] ?? '';
        $waId      = $message['id'] ?? '';
        $timestamp = $message['timestamp'] ?? time();

        if (empty($text) || empty($phone)) { return; }

        // Obtener o crear conversación
        $conversation = $this->getOrCreateConversation($phone);
        if (!$conversation) { return; }

        // Guardar mensaje entrante
        $this->saveMessage(
            (int) $conversation['id'], 'inbound', 'text', $text, $waId
        );

        // Si está escalada a humano, notificar al agente y no responder automáticamente
        if ($conversation['status'] === 'escalated') {
            $this->notifyAgent((int) $conversation['id'], $text);
            return;
        }

        // Detectar intención
        $intent     = $this->detectIntent($text, $conversation['language'] ?? 'es');
        $language   = $conversation['language'] ?? 'es';

        // Actualizar contexto
        $context             = json_decode($conversation['context'] ?? '{}', true);
        $context['last_intent']   = $intent;
        $context['message_count'] = ($context['message_count'] ?? 0) + 1;

        // ¿Escalar automáticamente?
        $escalateKeywords = explode(',', Configuration::get('SMART_CC_ESCALATE_KEYWORDS') ?: 'humano,persona');
        $shouldEscalate   = $intent === 'ESCALATE'
            || in_array(strtolower(trim($text)), $escalateKeywords)
            || ($context['message_count'] >= (int) Configuration::get('SMART_CC_ESCALATE_AFTER_MSGS'));

        if ($shouldEscalate) {
            $this->escalateToHuman((int) $conversation['id'], $phone);
            $response = self::AUTO_RESPONSES['ESCALATE'][$language]
                     ?? self::AUTO_RESPONSES['ESCALATE']['es'];
        } else {
            $response = $this->buildBotResponse($intent, $language, $conversation, $context);
        }

        // Actualizar contexto en BD
        Db::getInstance()->update('smart_concierge_conversation', [
            'context'         => pSQL(json_encode($context)),
            'last_message_at' => date('Y-m-d H:i:s'),
        ], 'id = ' . (int) $conversation['id']);

        // Enviar respuesta
        if ($response) {
            $this->sendWhatsAppMessage($phone, $response);
            $this->saveMessage((int) $conversation['id'], 'outbound', 'text', $response);
        }

        // Crear solicitud si el intent lo requiere
        $this->maybeCreateRequest((int) $conversation['id'], $intent, $text, $conversation);
    }

    // ──────────────────────────────────────────────────────────
    //  MOTOR DE INTENCIONES (reglas básicas, expandible con IA)
    // ──────────────────────────────────────────────────────────

    /**
     * Detectar intención del mensaje usando reglas de palabras clave.
     * En Fase 4 se reemplazará por un modelo de NLP/LLM.
     */
    public function detectIntent(string $text, string $lang = 'es'): string
    {
        $text = mb_strtolower(trim($text));

        $patterns = [
            'WIFI_INFO'         => ['wifi', 'contraseña', 'clave', 'internet', 'password', 'red'],
            'CHECKOUT_TIME'     => ['hora de salida', 'hora checkout', 'hora check-out', 'cuando debo salir'],
            'LATE_CHECKOUT'     => ['late checkout', 'salida tarde', 'extender', 'hora de salida más tarde'],
            'ROOM_SERVICE'      => ['room service', 'servicio habitación', 'pedir comida', 'desayuno habitación', 'cenar habitación'],
            'RESTAURANT_RES'    => ['reserva restaurante', 'mesa', 'cena', 'almuerzo', 'breakfast', 'restaurant'],
            'SPA_BOOKING'       => ['spa', 'masaje', 'tratamiento', 'relajarse', 'bienestar', 'piscina'],
            'TRANSPORT'         => ['taxi', 'transfer', 'aeropuerto', 'transporte', 'auto', 'uber', 'remis'],
            'MAINTENANCE_ISSUE' => ['no funciona', 'roto', 'problema', 'falla', 'fría el agua', 'calefacción', 'luz', 'tv no'],
            'EXTRA_TOWELS'      => ['toalla', 'blancos', 'almohada', 'sábana', 'ropa de cama'],
            'WAKE_UP_CALL'      => ['despertador', 'despertar', 'wake up', 'llamada mañana'],
            'TOURIST_INFO'      => ['qué hacer', 'recomienda', 'visitar', 'turismo', 'excursión', 'paseo'],
            'ESCALATE'          => ['hablar con persona', 'recepción', 'humano', 'operador', 'no entiendes'],
        ];

        foreach ($patterns as $intent => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($text, $kw)) {
                    return $intent;
                }
            }
        }

        return 'GENERAL_HELP';
    }

    /**
     * Construir respuesta del bot según intención.
     */
    private function buildBotResponse(
        string $intent, string $lang,
        array $conversation, array $context
    ): string {
        $roomNumber   = $conversation['room_number'] ?? '---';
        $checkoutTime = Configuration::get('SMART_FD_CHECKOUT_TIME') ?: '12:00';
        $botName      = Configuration::get('SMART_CC_BOT_NAME') ?: 'Sofia';
        $hotelName    = Configuration::get('PS_SHOP_NAME') ?: 'Hotel Smart';

        $template = self::AUTO_RESPONSES[$intent][$lang]
            ?? self::AUTO_RESPONSES[$intent]['es']
            ?? self::AUTO_RESPONSES['GENERAL_HELP']['es'];

        // Reemplazar variables
        return str_replace(
            ['{ROOM_NUM}', '{CHECKOUT_TIME}', '{BOT_NAME}', '{HOTEL_NAME}'],
            [$roomNumber,   $checkoutTime,     $botName,     $hotelName],
            $template
        );
    }

    // ──────────────────────────────────────────────────────────
    //  WHATSAPP CLOUD API — Envío
    // ──────────────────────────────────────────────────────────

    /**
     * Enviar mensaje de texto vía WhatsApp Cloud API.
     */
    public function sendWhatsAppMessage(string $phone, string $text): bool
    {
        $phoneId = Configuration::get('SMART_NOTIF_WHATSAPP_PHONE_ID');
        $token   = Configuration::get('SMART_NOTIF_WHATSAPP_TOKEN');
        $apiUrl  = Configuration::get('SMART_NOTIF_WHATSAPP_API_URL') ?: 'https://graph.facebook.com/v18.0';

        if (!$phoneId || !$token) { return false; }

        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'to'                => preg_replace('/[^0-9]/', '', $phone),
            'type'              => 'text',
            'text'              => ['body' => $text, 'preview_url' => false],
        ]);

        $ch = curl_init("{$apiUrl}/{$phoneId}/messages");
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ],
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200) {
            $res = json_decode($response, true);
            return isset($res['messages'][0]['id']);
        }

        PrestaShopLogger::addLog('SmartConcierge WA send error ' . $code . ': ' . $response, 3);
        return false;
    }

    // ──────────────────────────────────────────────────────────
    //  HOOKS
    // ──────────────────────────────────────────────────────────

    /** Enviar mensaje de bienvenida al hacer check-in */
    public function hookActionSmartCheckIn(array $params): void
    {
        if (!Configuration::get('SMART_CC_BOT_ENABLED')) { return; }

        $idBooking  = (int) ($params['id_booking'] ?? 0);
        $idCustomer = (int) Db::getInstance()->getValue(
            'SELECT id_customer FROM `' . _DB_PREFIX_ . 'htl_booking_detail`
             WHERE id = ' . $idBooking
        );
        if (!$idCustomer) { return; }

        $customer = new Customer($idCustomer);
        $phone    = $customer->phone ?: $customer->phone_mobile;
        if (!$phone) { return; }

        $hotelName = Configuration::get('PS_SHOP_NAME') ?: 'Hotel Smart';
        $botName   = Configuration::get('SMART_CC_BOT_NAME') ?: 'Sofia';
        $wifiPass  = 'smart' . ($params['room_number'] ?? '000') . '2024';

        $welcomeMsg = "🏨 *¡Bienvenido/a al {$hotelName}!*\n\n"
            . "Estimado/a {$customer->firstname}, su habitación está lista para recibirle.\n\n"
            . "📶 *WiFi:* SmartHotel_Guest | Contraseña: {$wifiPass}\n"
            . "🕐 *Check-out:* " . (Configuration::get('SMART_FD_CHECKOUT_TIME') ?: '12:00') . "h\n\n"
            . "Soy *{$botName}*, su concierge digital personal. Estoy disponible 24/7 para cualquier solicitud. "
            . "Solo escríbame aquí mismo. ¿Necesita algo?\n\n"
            . "_Powered by SmartHotelOS_";

        $this->sendWhatsAppMessage($phone, $welcomeMsg);
    }

    // ──────────────────────────────────────────────────────────
    //  HELPERS PRIVADOS
    // ──────────────────────────────────────────────────────────

    private function getOrCreateConversation(string $phone): ?array
    {
        $conv = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'smart_concierge_conversation`
             WHERE wa_phone = "' . pSQL($phone) . '" AND status != "closed"
             ORDER BY last_message_at DESC LIMIT 1'
        );

        if ($conv) { return $conv; }

        // Buscar cliente por teléfono
        $idCustomer = (int) Db::getInstance()->getValue(
            'SELECT id_customer FROM `' . _DB_PREFIX_ . 'customer`
             WHERE phone = "' . pSQL($phone) . '" OR phone_mobile = "' . pSQL($phone) . '"
             LIMIT 1'
        );

        // Buscar reserva activa
        $idBooking = $idCustomer ? (int) Db::getInstance()->getValue(
            'SELECT id FROM `' . _DB_PREFIX_ . 'htl_booking_detail`
             WHERE id_customer = ' . $idCustomer . '
             AND booking_status IN (1,2) AND booking_date_to >= CURDATE()
             ORDER BY booking_date_from ASC LIMIT 1'
        ) : 0;

        // Detectar idioma (simplificado)
        $language = 'es';

        Db::getInstance()->insert('smart_concierge_conversation', [
            'wa_phone'    => pSQL($phone),
            'id_customer' => $idCustomer ?: null,
            'id_booking'  => $idBooking ?: null,
            'language'    => $language,
            'status'      => 'bot',
            'context'     => '{}',
        ]);

        return Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'smart_concierge_conversation`
             WHERE id = ' . (int) Db::getInstance()->Insert_ID()
        );
    }

    private function saveMessage(
        int $idConversation, string $direction, string $type,
        string $content, string $waId = ''
    ): void {
        Db::getInstance()->insert('smart_concierge_message', [
            'id_conversation' => $idConversation,
            'direction'       => pSQL($direction),
            'message_type'    => pSQL($type),
            'content'         => pSQL($content),
            'wa_message_id'   => pSQL($waId),
            'handled_by'      => 'bot',
        ]);
    }

    private function escalateToHuman(int $idConversation, string $phone): void
    {
        Db::getInstance()->update('smart_concierge_conversation',
            ['status' => 'escalated'],
            'id = ' . $idConversation
        );

        // Notificar al equipo de recepción
        Hook::exec('actionSmartSendNotification', [
            'type'    => 'guest_request',
            'title'   => 'Huésped solicita hablar con persona',
            'message' => "WhatsApp {$phone} quiere ser atendido por un agente.",
        ]);
    }

    private function notifyAgent(int $idConversation, string $text): void
    {
        // Notificación interna al agente asignado
        Hook::exec('actionSmartSendNotification', [
            'type'    => 'guest_request',
            'title'   => 'Nuevo mensaje en conversación escalada',
            'message' => substr($text, 0, 200),
            'data'    => ['id_conversation' => $idConversation],
        ]);
    }

    private function maybeCreateRequest(
        int $idConversation, string $intent,
        string $text, array $conversation
    ): void {
        $requestIntents = [
            'ROOM_SERVICE', 'TRANSPORT', 'RESTAURANT_RES', 'SPA_BOOKING',
            'MAINTENANCE_ISSUE', 'EXTRA_TOWELS', 'WAKE_UP_CALL',
        ];

        if (!in_array($intent, $requestIntents)) { return; }

        Db::getInstance()->insert('smart_concierge_request', [
            'id_conversation' => $idConversation,
            'id_property'     => (int) ($conversation['id_property'] ?? 1),
            'id_booking'      => isset($conversation['id_booking']) ? (int) $conversation['id_booking'] : null,
            'request_type'    => pSQL($intent),
            'description'     => pSQL($text),
            'status'          => 'open',
        ]);
    }

    private function updateMessageStatus(array $status): void
    {
        if (!empty($status['id'])) {
            Db::getInstance()->update('smart_concierge_message',
                ['wa_message_id' => pSQL($status['id'])],
                'wa_message_id = "' . pSQL($status['id']) . '"'
            );
        }
    }

    public function getContent(): string { return $this->display(__FILE__, 'views/templates/admin/configuration.tpl'); }
}
