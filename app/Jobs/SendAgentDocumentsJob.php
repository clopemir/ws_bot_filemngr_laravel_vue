<?php

namespace App\Jobs;

use App\Models\Agent;
use App\Models\Client;
use App\Models\File;
use App\Services\WhatsAppService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Envía a un agente documentos de un cliente. Cada archivo indica de qué cliente es,
 * porque un agente puede pedir documentos de varios clientes en la misma conversación.
 */
class SendAgentDocumentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    // Un reintento duplicaría documentos ya enviados.
    public $tries = 1;

    public $timeout = 300;

    /**
     * @param  int[]  $fileIds
     */
    public function __construct(
        public int $agentId,
        public int $clientId,
        public array $fileIds,
        public string $label,
    ) {
    }

    public function handle(WhatsAppService $whatsAppService): void
    {
        $agent = Agent::find($this->agentId);
        $client = Client::find($this->clientId);

        // El agente pudo darse de baja mientras el job esperaba en la cola.
        if (!$agent || !$agent->agent_status || !$client) {
            Log::channel('security')->warning('Envío a agente cancelado.', ['agent_id' => $this->agentId, 'client_id' => $this->clientId]);
            return;
        }

        $clientName = trim("{$client->client_name} {$client->client_lname}");
        $delayMs = (int) config('whatsapp_bot.document_send_delay_ms', 1000);
        $sent = 0;

        $files = File::whereIn('id', $this->fileIds)->where('client_rfc', $client->client_rfc)->get();

        foreach ($files as $file) {
            try {
                if (!$file->storageDisk()) {
                    Log::error('Archivo no encontrado en storage.', ['file_id' => $file->id]);
                    continue;
                }

                $whatsAppService->sendDocument(
                    $agent->agent_phone,
                    $file->temporaryDownloadUrl(),
                    $file->original_file_name,
                    "{$clientName} · {$file->category}"
                );
                $sent++;

                usleep($delayMs * 1000);
            } catch (Exception $e) {
                Log::error("Error al enviar documento {$file->id} a un agente: " . $e->getMessage());
            }
        }

        Log::channel('security')->info('Documentos enviados a un agente.', [
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'assigned_to_agent' => $client->agent_id === $agent->id,
            'label' => $this->label,
            'file_ids' => $files->pluck('id')->all(),
            'count' => $sent,
        ]);

        if ($sent < count($this->fileIds)) {
            $whatsAppService->sendTextMessage($agent->agent_phone, trans('whatsapp.agent.partial_delivery', [
                'sent' => $sent,
                'total' => count($this->fileIds),
                'client' => $clientName,
            ]));
        }
    }
}
