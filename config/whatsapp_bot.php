<?php

return [
    /*
    |--------------------------------------------------------------------------
    | URLs y Configuraciones del Bot de WhatsApp
    |--------------------------------------------------------------------------
    */

    $headerImage = config('app.url') . '/images/bot.png',
    // URL de la imagen de cabecera para los mensajes de bienvenida.
    // Debe ser una URL pública accesible por los servidores de WhatsApp.
    'welcome_image_url' => env('WHATSAPP_WELCOME_IMAGE_URL', $headerImage),

    // Minutos de inactividad antes de considerar enviar un recordatorio a un chat.
    // (Usado por el Job programado `sendScheduledReminders`)
    'chat_inactivity_minutes' => 15,
];