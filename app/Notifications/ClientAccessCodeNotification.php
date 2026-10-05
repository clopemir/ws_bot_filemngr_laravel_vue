<?php

namespace App\Notifications;

use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Código de verificación enviado al correo registrado del titular cuando alguien
 * solicita sus documentos desde un número de WhatsApp no registrado.
 */
class ClientAccessCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Client $client,
        public string $code,
        public string $maskedRequesterPhone,
        public int $ttlMinutes,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Código de verificación para acceder a tus documentos')
            ->greeting("Hola {$this->client->client_name}")
            ->line("Se solicitó acceso a tus documentos de " . config('app.name') . " por WhatsApp desde el número {$this->maskedRequesterPhone}, que no está registrado en tu expediente.")
            ->line("Si fuiste tú o autorizas a esa persona, comparte este código (vence en {$this->ttlMinutes} minutos):")
            ->line("**{$this->code}**")
            ->line('Si no reconoces esta solicitud, ignora este correo: nadie podrá acceder sin el código. Te recomendamos avisar a tu agente.')
            ->salutation('Atentamente, ' . config('app.name'));
    }
}
