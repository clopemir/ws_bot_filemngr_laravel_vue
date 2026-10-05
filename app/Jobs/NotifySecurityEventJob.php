<?php

namespace App\Jobs;

use App\Models\Client;
use App\Services\WhatsAppService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Avisa al agente del cliente sobre eventos de seguridad en el bot.
 */
class NotifySecurityEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const UNREGISTERED_PHONE = 'unregistered_phone';
    public const OTP_VERIFIED = 'otp_verified';
    public const OTP_LOCKED = 'otp_locked';

    public $tries = 3;

    public $backoff = 60;

    public function __construct(
        public Client $client,
        public string $event,
        public string $requesterPhone,
    ) {
    }

    public function handle(WhatsAppService $whatsAppService): void
    {
        $agent = $this->client->agent;

        if (!$agent || !$agent->agent_phone) {
            Log::channel('security')->warning('Evento de seguridad sin agente al cual notificar.', [
                'client_id' => $this->client->id,
                'event' => $this->event,
            ]);
            return;
        }

        try {
            $whatsAppService->sendTextMessage($agent->agent_phone, trans("whatsapp.security_alerts.{$this->event}", [
                'agent_name' => $agent->agent_name,
                'client_name' => trim("{$this->client->client_name} {$this->client->client_lname}"),
                'phone' => $this->requesterPhone,
                'timestamp' => now()->translatedFormat('l, d F Y H:i'),
            ]));
        } catch (Exception $e) {
            Log::error("Fallo al notificar evento de seguridad al agente {$agent->id}: " . $e->getMessage());
            throw $e;
        }
    }
}
