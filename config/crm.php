<?php

return [
    // Código de país usado para normalizar teléfonos locales (El Salvador = 503).
    'default_country_code' => env('CRM_COUNTRY_CODE', '503'),
    'local_phone_length' => 8,

    // Validar el dígito verificador del DUI en formularios e importaciones.
    'validate_dui_checksum' => (bool) env('CRM_VALIDATE_DUI_CHECKSUM', true),

    // Importaciones: a partir de cuántas filas se procesa en cola en lugar de inmediatamente.
    'import_queue_threshold' => (int) env('CRM_IMPORT_QUEUE_THRESHOLD', 300),
    'import_max_preview_rows' => 50,

    /*
    | Valores por defecto de la configuración administrable (tabla system_settings).
    | Formato: clave => [grupo, tipo, valor por defecto]
    */
    'settings' => [
        // Empresa
        'company.name' => ['company', 'string', 'Comercial El Progreso S.A. de C.V.'],
        'company.phone' => ['company', 'string', '2200-0000'],
        'company.email' => ['company', 'string', 'atencion@empresa.test'],
        'company.address' => ['company', 'text', 'San Salvador, El Salvador'],
        'company.website' => ['company', 'string', ''],

        // WhatsApp
        'whatsapp.provider' => ['whatsapp', 'string', env('WHATSAPP_PROVIDER', 'simulator')],
        'whatsapp.rate_limit_per_minute' => ['whatsapp', 'int', 60],
        'whatsapp.simulator_failure_rate' => ['whatsapp', 'int', 5],
        'whatsapp.meta_phone_number_id' => ['whatsapp', 'string', ''],
        'whatsapp.meta_business_account_id' => ['whatsapp', 'string', ''],
        'whatsapp.meta_access_token' => ['whatsapp', 'secret', ''],
        'whatsapp.meta_app_secret' => ['whatsapp', 'secret', ''],
        'whatsapp.meta_verify_token' => ['whatsapp', 'secret', ''],
        'whatsapp.twilio_account_sid' => ['whatsapp', 'string', ''],
        'whatsapp.twilio_auth_token' => ['whatsapp', 'secret', ''],
        'whatsapp.twilio_from_number' => ['whatsapp', 'string', ''],

        // Chatbot
        'chatbot.enabled' => ['chatbot', 'bool', true],
        'chatbot.verification_method' => ['chatbot', 'string', 'phone_match'], // phone_match | otp | contract_number
        'chatbot.verification_ttl_minutes' => ['chatbot', 'int', 30],
        'chatbot.max_failed_attempts' => ['chatbot', 'int', 3],
        'chatbot.lock_minutes' => ['chatbot', 'int', 30],
        'chatbot.welcome_message' => ['chatbot', 'text', "¡Hola! 👋 Bienvenido(a) a {{empresa}}.\n\nPuedo ayudarte con:\n1️⃣ Consultar cuánto debes\n2️⃣ Consultar tus artículos\n3️⃣ Consultar cuotas pendientes\n4️⃣ Consultar tu próxima fecha de pago\n5️⃣ Hablar con un asesor\n6️⃣ Información general\n\nEscribe el número de la opción o tu consulta."],
        'chatbot.fallback_message' => ['chatbot', 'text', "Disculpa, no logré entender tu mensaje. 🤔\n\nEscribe *menú* para ver las opciones disponibles o *asesor* para hablar con una persona."],
        'chatbot.ask_dui_message' => ['chatbot', 'text', 'Con gusto te ayudamos. Para consultar tu información, por favor ingresa tu número de DUI (ejemplo: 01234567-8).'],
        'chatbot.agent_transfer_message' => ['chatbot', 'text', 'Entendido. 🙋 Te estamos transfiriendo con un asesor. En breve una persona de nuestro equipo continuará la conversación.'],
        'chatbot.out_of_hours_message' => ['chatbot', 'text', 'Nuestro horario de atención es de {{horario}}. Tu solicitud quedó registrada y un asesor te contactará en cuanto estemos disponibles.'],
        'chatbot.returned_to_bot_message' => ['chatbot', 'text', 'Gracias por comunicarte con nosotros. Si necesitas algo más, escribe *menú* para ver las opciones.'],

        // Horario de atención
        'hours.timezone' => ['hours', 'string', 'America/El_Salvador'],
        'hours.schedule' => ['hours', 'json', [
            'mon' => ['08:00', '17:00'], 'tue' => ['08:00', '17:00'], 'wed' => ['08:00', '17:00'],
            'thu' => ['08:00', '17:00'], 'fri' => ['08:00', '17:00'], 'sat' => ['08:00', '12:00'], 'sun' => null,
        ]],

        // Notificaciones internas
        'notifications.agent_requested' => ['notifications', 'bool', true],
        'notifications.new_conversation' => ['notifications', 'bool', true],
        'notifications.campaign_finished' => ['notifications', 'bool', true],
        'notifications.import_failed' => ['notifications', 'bool', true],
        'notifications.messages_failed' => ['notifications', 'bool', true],
        'notifications.conversation_assigned' => ['notifications', 'bool', true],

        // Preferencias de comunicación
        'communication.opt_out_keywords' => ['communication', 'string', 'STOP, BAJA, NO MAS MENSAJES, CANCELAR SUSCRIPCION'],
        'communication.opt_out_message' => ['communication', 'text', 'Has sido dado de baja de nuestras comunicaciones promocionales. Si deseas volver a recibirlas, comunícate con nosotros.'],
        'communication.collection_send_from' => ['communication', 'string', '08:00'],
        'communication.collection_send_until' => ['communication', 'string', '19:00'],
        'communication.min_hours_between_collection_messages' => ['communication', 'int', 24],

        // Cobranza
        'collection.auto_recalculate_enabled' => ['collection', 'bool', false],
    ],
];
