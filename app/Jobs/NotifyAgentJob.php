<?php

namespace App\Jobs;

use App\Models\Client;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class NotifyAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * El número de veces que el job puede ser reintentado.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * El número de segundos a esperar antes de reintentar el job.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(public Client $client)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(WhatsAppService $whatsAppService): void
    {
        if (!$this->client->agent || !$this->client->agent->agent_phone) {
            Log::warning("El cliente {$this->client->id} solicitó un agente, pero el agente o su teléfono no están configurados.");
            // Aquí podrías notificar a un administrador.
            return;
        }

        try {
            $agent = $this->client->agent;
            $message = trans('whatsapp.agent_notification_message', [
                'agent_name' => $agent->agent_name,
                'client_name' => $this->client->client_name,
                'client_phone' => $this->client->client_phone,
                'timestamp' => now()->translatedFormat('l, d F Y H:i'),
            ]);

            $whatsAppService->sendTextMessage($agent->agent_phone, $message);

            Log::info("Notificación enviada exitosamente al agente {$agent->id} para el cliente {$this->client->id}.");

        } catch (Exception $e) {
            Log::error("Fallo al enviar notificación al agente {$this->client->agent->id}: " . $e->getMessage());
            // La excepción hará que Laravel reintente el job automáticamente.
            throw $e;
        }
    }
}
