<?php

/*
| Credenciales de proveedores de WhatsApp. Se leen exclusivamente de variables de entorno
| (o, en su defecto, de la configuración cifrada en base de datos). Nunca se escriben en código.
*/
return [
    'meta' => [
        'graph_url' => env('WHATSAPP_META_GRAPH_URL', 'https://graph.facebook.com'),
        'api_version' => env('WHATSAPP_META_API_VERSION', 'v23.0'),
    ],

    'twilio' => [
        'api_url' => env('TWILIO_API_URL', 'https://api.twilio.com/2010-04-01'),
    ],

    'env' => [
        'WHATSAPP_META_PHONE_NUMBER_ID' => env('WHATSAPP_META_PHONE_NUMBER_ID'),
        'WHATSAPP_META_BUSINESS_ACCOUNT_ID' => env('WHATSAPP_META_BUSINESS_ACCOUNT_ID'),
        'WHATSAPP_META_ACCESS_TOKEN' => env('WHATSAPP_META_ACCESS_TOKEN'),
        'WHATSAPP_META_APP_SECRET' => env('WHATSAPP_META_APP_SECRET'),
        'WHATSAPP_META_VERIFY_TOKEN' => env('WHATSAPP_META_VERIFY_TOKEN'),
        'TWILIO_ACCOUNT_SID' => env('TWILIO_ACCOUNT_SID'),
        'TWILIO_AUTH_TOKEN' => env('TWILIO_AUTH_TOKEN'),
        'TWILIO_WHATSAPP_FROM' => env('TWILIO_WHATSAPP_FROM'),
    ],
];
