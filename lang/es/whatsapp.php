<?php


return [
    'welcome' => "Hola *:name* ¡un gusto saludarte! 👋🏼\n\nSoy *PAI*, el Asistente Virtual de *FP Corporativo*, diseñado para ayudar 😉\n\nPor favor, selecciona una de las opciones para comenzar:",
    'welcome_footer' => 'Tu asistente virtual.',
    'request_rfc' => "Excelente!\n\nPara continuar necesitamos hacer una pequeña comprobación:\n\n*Por favor escribe tu RFC (ej: XAXX010101000)*.",
    'need_anything_else' => '¿Puedo ayudarte en algo más?',
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
        'body' => "Hola *:name* 👋🏼\n\nElige la categoría de documentos que deseas descargar para el RFC: *:rfc*",
        'footer' => 'Tus archivos disponibles',
    ],

    'agent_notification_pending' => 'Hemos notificado a tu agente. Se pondrá en contacto contigo pronto.',
    'agent_notification_message' => "Hola *:agent_name*.\n\nEl cliente *:client_name* solicita tu atención.\n\nContáctalo en: wa.me/:client_phone\n\nHora: :timestamp",

    'errors' => [
        'invalid_rfc' => "El formato del RFC no es válido.\n\nPor favor, inténtalo de nuevo\n(ej: *XAXX010101000*).",
        'rfc_not_found' => "El RFC ':rfc' no se encuentra en nuestros registros.\n\nIntenta de nuevo o selecciona '*Aún no soy cliente*'.",
        'option_not_recognized' => 'Opción no reconocida. Por favor, selecciona una de la lista.',
        'select_from_list' => 'Por favor, selecciona una opción válida de la lista.',
        'no_categories_found' => "No hay categorías de documentos disponibles para el RFC :rfc en este momento.",
        'no_files_in_category' => "No he encontrado archivos en la categoría ':category'.",
        'files_not_sent' => "Se encontraron registros para la categoría ':category', pero no se pudo enviar ningún archivo. Por favor, contacta a soporte.",
        'security_issue' => 'Hubo un problema de seguridad. Por favor, reinicia la conversación escribiendo "menú".',
        'generic_error' => 'Ocurrió un error inesperado. Hemos sido notificados. Por favor, intenta de nuevo más tarde.',
        'unsafe_url' => 'Hubo un error al preparar tu documento (URL no segura). Por favor, contacta a soporte.',
    ],
];