<?php


return [
    'welcome' => "Hola *:name* ¡un gusto saludarte! 👋🏼\n\nSoy *PAI*, el Asistente Virtual de *FP Corporativo*, diseñado para ayudar 😉\n\nPor favor, selecciona una de las opciones para comenzar:",
    'welcome_footer' => 'Tu asistente virtual.',
    'request_rfc' => "Excelente!\n\nPara continuar necesitamos hacer una pequeña comprobación:\n\n*Por favor escribe tu RFC (ej: XAXX010101000)*.",
    'need_anything_else' => "¿Puedo ayudarte en algo más?\n\nEscribe *menú* para terminar.",
    'goodbye' => "Gracias por contactarnos *:name*\n\nSi necesitas algo más, no dudes en escribirnos de nuevo. 👋🏼",
    'sending_files' => "Buscando archivos en la categoría ':category'.\nUn momento...",
    'files_sent_summary' => "Se han enviado :count archivos de la categoría ':category'.",

    'buttons' => [
        'is_client' => 'Soy Cliente',
        'is_not_client' => 'Aún no soy Cliente',
        'view_options' => 'Ver Opciones',
        'view_categories' => 'Ver Categorías',
    ],

    'client_options' => [
        'header' => 'Opciones para Clientes',
        'body' => "Hola *:name* 👋🏼\n\nEstas son tus opciones disponibles como cliente de FP Corporativo:",
        'footer' => 'Selecciona una opción',
        'main_services_title' => 'Servicios Principales',
    ],

    'doc_categories' => [
        'header' => 'Selecciona Categoría',
        'body' => "Hola *:name* 👋🏼\n\nElige la categoría de documentos que deseas descargar:",
        'footer' => 'Tus archivos disponibles',
    ],

    'otp' => [
        'sent' => "🔐 *Verificación adicional*\n\nEste número de WhatsApp no está registrado para el RFC indicado.\n\nSi el RFC corresponde a un cliente, enviamos un *código de 6 dígitos* a los medios de contacto registrados del titular. Pídele el código y escríbelo aquí (vence en :minutes minutos).\n\nSi no recibes el código, verifica el RFC escribiendo *menú* o pide a tu agente que registre este número.",
        'prompt' => 'Escribe el código de 6 dígitos que recibió el titular, o *menú* para cancelar.',
        'verified' => '✅ Código verificado. Acceso concedido temporalmente.',
        'invalid' => "Código incorrecto. Te quedan *:remaining* intento(s).",
        'locked' => "Se agotaron los intentos. Por seguridad cancelamos la verificación y avisamos al agente del titular.",
        'expired' => "El código no es válido o ya venció. Escribe *menú* para comenzar de nuevo.",
    ],

    'security_alerts' => [
        'unregistered_phone' => "⚠️ *Alerta de seguridad*\n\nHola *:agent_name*. Se solicitó acceso a los documentos de *:client_name* desde un número NO registrado: wa.me/:phone\n\nSe envió un código de verificación a los medios registrados del cliente.\n\nHora: :timestamp",
        'otp_verified' => "ℹ️ El número wa.me/:phone accedió a los documentos de *:client_name* con un código de verificación.\n\nSi el cliente usará este número de forma habitual, actualiza su teléfono en el panel.\n\nHora: :timestamp",
        'otp_locked' => "🚫 *Alerta de seguridad*\n\nEl número wa.me/:phone agotó los intentos de código para *:client_name*. Considera contactar al cliente.\n\nHora: :timestamp",
    ],

    'agent_already_notified' => 'Tu agente ya fue notificado y se pondrá en contacto contigo. Mientras tanto, estas son tus opciones:',
    'agent_notification_pending' => 'Hemos notificado a tu agente. Se pondrá en contacto contigo pronto.',
    'agent_notification_message' => "Hola *:agent_name*.\n\nEl cliente *:client_name* solicita tu atención.\n\nContáctalo en: wa.me/:client_phone\n\nHora: :timestamp",

    'errors' => [
        'invalid_rfc' => "El formato del RFC no es válido.\n\nPor favor, inténtalo de nuevo\n(ej: *XAXX010101000*).",
        'rfc_not_found' => "El RFC ':rfc' no se encuentra en nuestros registros.\n\nIntenta de nuevo o escribe *menú* para salir.",
        'too_many_attempts' => "Por seguridad, este número alcanzó el límite de intentos. Intenta de nuevo en :minutes minuto(s) o contacta a tu agente.",
        'session_expired' => "🔒 Tu sesión expiró por seguridad.\n\nPor favor escribe nuevamente tu RFC para continuar.",
        'option_not_recognized' => 'Opción no reconocida. Por favor, selecciona una de la lista.',
        'select_from_list' => 'Por favor, selecciona una opción válida de la lista.',
        'no_categories_found' => "No hay documentos disponibles en este momento.",
        'no_files_in_category' => "No he encontrado archivos en la categoría ':category'.",
        'files_not_sent' => "Se encontraron registros para la categoría ':category', pero no se pudo enviar ningún archivo. Por favor, contacta a soporte.",
        'security_issue' => 'Hubo un problema de seguridad. Por favor, reinicia la conversación escribiendo "menú".',
        'generic_error' => 'Ocurrió un error inesperado. Hemos sido notificados. Por favor, intenta de nuevo más tarde.',
        'unsafe_url' => 'Hubo un error al preparar tu documento (URL no segura). Por favor, contacta a soporte.',
    ],
];