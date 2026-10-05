<?php

namespace App\Jobs;

use App\Models\Chat;
use App\Models\File as ClientFile;
use App\Services\WhatsAppService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Envía al chat los documentos de una categoría usando enlaces firmados de corta duración.
 */
class SendClientDocumentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    // Un reintento duplicaría documentos ya enviados.
    public $tries = 1;

    public $timeout = 300;

    public function __construct(
        public int $chatId,
        public int $clientId,
        public string $category,
    ) {
    }

    public function handle(WhatsAppService $whatsAppService): void
    {
        $chat = Chat::with('client')->find($this->chatId);

        // La sesión pudo cerrarse o cambiar de cliente mientras el job esperaba en la cola.
        if (!$chat || $chat->client_id !== $this->clientId || !$chat->hasValidSession()) {
            Log::warning('Envío de documentos cancelado: la sesión ya no es válida.', ['chat_id' => $this->chatId]);
            return;
        }

        $files = ClientFile::where('client_rfc', $chat->client->client_rfc)
            ->where('category', $this->category)
            ->get();

        if ($files->isEmpty()) {
            $whatsAppService->sendTextMessage($chat->user_phone, trans('whatsapp.errors.no_files_in_category', ['category' => $this->category]));
            return;
        }

        $delayMs = (int) config('whatsapp_bot.document_send_delay_ms', 1000);
        $sent = 0;

        foreach ($files as $file) {
            try {
                if (!$file->storageDisk()) {
                    Log::error('Archivo no encontrado en storage.', ['file_id' => $file->id]);
                    continue;
                }

                $whatsAppService->sendDocument(
                    $chat->user_phone,
                    $file->temporaryDownloadUrl(),
                    $file->original_file_name,
                    "Archivo de {$this->category}: {$file->original_file_name}"
                );
                $sent++;

                usleep($delayMs * 1000); // Pausa entre envíos para evitar el rate limiting de la API
            } catch (Exception $e) {
                Log::error("Error al enviar documento {$file->id}: " . $e->getMessage());
            }
        }

        Log::channel('security')->info('Documentos enviados por WhatsApp.', [
            'chat_id' => $chat->id,
            'client_id' => $chat->client_id,
            'category' => $this->category,
            'count' => $sent,
            'method' => $chat->verification_method,
        ]);

        $whatsAppService->sendTextMessage($chat->user_phone, $sent > 0
            ? trans('whatsapp.files_sent_summary', ['count' => $sent, 'category' => $this->category])
            : trans('whatsapp.errors.files_not_sent', ['category' => $this->category]));

        $whatsAppService->sendTextMessage($chat->user_phone, trans('whatsapp.need_anything_else'));
    }
}
