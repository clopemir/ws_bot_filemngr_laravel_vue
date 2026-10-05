<?php

return [
    /*
    |--------------------------------------------------------------------------
    | URLs y Configuraciones del Bot de WhatsApp
    |--------------------------------------------------------------------------
    */

    // URL de la imagen de cabecera para los mensajes de bienvenida.
    // Debe ser una URL pública accesible por los servidores de WhatsApp.
    'welcome_image_url' => env('WHATSAPP_WELCOME_IMAGE_URL', env('APP_URL') . '/images/bot.png'),

    // Minutos de inactividad antes de considerar enviar un recordatorio a un chat.
    'chat_inactivity_minutes' => 15,

    // Pausa (en milisegundos) entre el envío de cada documento para no saturar la API.
    'document_send_delay_ms' => env('WHATSAPP_DOCUMENT_DELAY_MS', 1000),

    /*
    |--------------------------------------------------------------------------
    | Seguridad del flujo de clientes
    |--------------------------------------------------------------------------
    |
    | Factor 1: el número de WhatsApp que escribe debe coincidir con el
    |           teléfono registrado del cliente (Meta garantiza la autenticidad
    |           del número porque el webhook va firmado).
    | Factor 2: si el RFC llega desde un número NO registrado, se envía un
    |           código de un solo uso a los medios registrados del titular
    |           (correo y/o plantilla de WhatsApp a su número registrado).
    |
    */
    'security' => [
        // Minutos que dura una sesión verificada antes de pedir de nuevo el RFC.
        'session_ttl_minutes' => (int) env('WHATSAPP_SESSION_TTL_MINUTES', 15),

        // Intentos de RFC por número antes de bloquearlo temporalmente.
        'rfc_max_attempts' => (int) env('WHATSAPP_RFC_MAX_ATTEMPTS', 5),
        'rfc_decay_minutes' => (int) env('WHATSAPP_RFC_DECAY_MINUTES', 60),

        // Código de verificación (segundo factor).
        'otp_length' => 6,
        'otp_ttl_minutes' => (int) env('WHATSAPP_OTP_TTL_MINUTES', 10),
        'otp_max_attempts' => (int) env('WHATSAPP_OTP_MAX_ATTEMPTS', 3),
        // Máximo de códigos que se pueden generar por cliente en 24 h (evita spam al titular).
        'otp_max_per_client_per_day' => (int) env('WHATSAPP_OTP_MAX_PER_DAY', 3),

        // Canales para entregar el código al titular.
        'otp_via_email' => (bool) env('WHATSAPP_OTP_VIA_EMAIL', true),
        // Nombre de una plantilla de "Autenticación" aprobada en Meta. Vacío = no se usa WhatsApp.
        'otp_whatsapp_template' => env('WHATSAPP_OTP_TEMPLATE'),
        'otp_whatsapp_template_language' => env('WHATSAPP_OTP_TEMPLATE_LANGUAGE', 'es_MX'),

        // Vigencia de los enlaces firmados con los que WhatsApp descarga los documentos.
        'download_link_ttl_minutes' => (int) env('WHATSAPP_DOWNLOAD_LINK_TTL_MINUTES', 10),

        // Dígitos finales que se comparan para decidir si dos teléfonos son el mismo.
        'phone_match_digits' => 10,
    ],
];
