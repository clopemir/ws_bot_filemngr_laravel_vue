<?php

namespace App\Services;

use Exception;
use App\Models\Chat;
use App\Models\Client;
use Illuminate\Support\Str;
use App\Jobs\NotifyAgentJob;
use App\Models\File as ClientFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ChatStateService
{
    public function __construct(
        private WhatsAppService $whatsAppService,
        private ClientService $clientService,
        private GeminiService $geminiService
    ) {

        $this->whatsAppService = $whatsAppService;
        $this->clientService = $clientService;
        $this->geminiService = $geminiService;
    }

    /**
     * Maneja el estado actual del chat basado en la acción y la intención.
     */
    public function handleState(Chat $chat, object $payload, object $intent): void
    {
        switch ($chat->action) {
            case Chat::ACTION_BASE:
                $this->handleBaseState($chat, $payload, $intent);
                break;
            case Chat::ACTION_REQUEST_RFC:
                $this->handleRfcRequestState($chat, $payload);
                break;
            case Chat::ACTION_CLIENT_OPTIONS:
                $this->handleClientOptionsState($chat, $payload, $intent);
                break;
            case Chat::ACTION_REQUEST_DOCUMENT_CATEGORY:
                $this->handleRequestDocumentCategoryState($chat, $payload, $intent);
                break;
            case Chat::ACTION_IA_CONVERSATION:
                $this->handleIaConversationState($chat, $payload);
                break;
            // Otros estados...
            default:
                Log::warning("Acción de chat desconocida: {$chat->action} para el chat ID: {$chat->id}");
                $this->resetChat($chat);
                break;
        }
    }

    /**
     * Resetea el chat al estado inicial y envía los botones de bienvenida.
     */
    public function resetChat(Chat $chat): void
    {
        $chat->update(['action' => Chat::ACTION_BASE, 'client_rfc' => null, 'client_id' => null]);

        $this->whatsAppService->sendTextMessage($chat->user_phone, "Gracias por contactarnos *{$chat->user_name}*\n\nSi necesitas algo más, no dudes en escribirnos de nuevo. 👋🏼");
    }

    // --- MANEJADORES DE ESTADO ---

    private function handleBaseState(Chat $chat, object $payload, object $intent): void
    {
        $this->whatsAppService->sendTypingIndicator($payload->messageId);

        if ($payload->userMessage === 'client') {
            $chat->update(['action' => Chat::ACTION_REQUEST_RFC]);
            $this->whatsAppService->sendTextMessage($payload->userPhone, trans('whatsapp.request_rfc'));
        } elseif ($intent->name === 'is_not_client') {
            // Lógica para no clientes
            $this->whatsAppService->sendInfo($payload->userPhone, $payload->userName);
            $this->resetChat($chat);
        } else {
            // Si no se reconoce la intención, se reenvían los botones.
            $this->whatsAppService->sendInitialButtons($payload->userPhone, $payload->userName);
        }
    }

    private function handleRfcRequestState(Chat $chat, object $payload): void
    {
        $rfc = Str::upper(Str::squish($payload->userMessage));

        if (!$this->clientService->isValidRfcFormat($rfc)) {
            $this->whatsAppService->sendTypingIndicator($payload->messageId);

            $this->whatsAppService->sendTextMessage($payload->userPhone, trans('whatsapp.errors.invalid_rfc'));
            return; // Mantenemos el estado para que reintente.
        }

        $client = $this->clientService->getClientByRfc($rfc);

        if ($client) {
            $chat->update([
                'client_rfc' => Str::lower($rfc),
                'client_id' => $client->id,
                'is_client' => true,
                'action' => Chat::ACTION_CLIENT_OPTIONS,
            ]);
            try {
                $this->whatsAppService->sendTypingIndicator($payload->messageId);


                $this->whatsAppService->sendClientOptions($payload->userPhone, $client->client_name, Str::lower($rfc));

            } catch (Exception $e) {
                Log::error("Error al enviar opciones al cliente {$client->id} ({$rfc}): " . $e->getMessage(), [
                    'wa_id' => $payload->waId,
                    'user_phone' => $payload->userPhone,
                ]);
            }

        } else {
            $this->whatsAppService->sendTypingIndicator($payload->messageId);

            $this->whatsAppService->sendTextMessage($payload->userPhone, trans('whatsapp.errors.rfc_not_found', ['rfc' => $rfc]));
            $this->resetChat($chat);
        }
    }

    private function handleClientOptionsState(Chat $chat, object $payload, object $intent): void
    {
        if ($intent->name === 'ask_doc_categories') {

            $this->whatsAppService->sendTypingIndicator($payload->messageId);

            $categories = ClientFile::where('client_rfc', $intent->data['rfc'])
                ->whereNotNull('category')->where('category', '!=', '')
                ->distinct()->pluck('category')->all();

            if (empty($categories)) {
                $this->whatsAppService->sendTextMessage($payload->userPhone, trans('whatsapp.errors.no_categories_found', ['rfc' => $intent->data['rfc']]));
                $this->whatsAppService->sendClientOptions($payload->userPhone, $payload->userName, $chat->client_rfc); // Volver a mostrar opciones
                return;
            }

            $chat->update(['action' => Chat::ACTION_REQUEST_DOCUMENT_CATEGORY]);
            $this->whatsAppService->sendDocumentCategoryOptions($payload->userPhone, $payload->userName, $intent->data['rfc'], $categories);

        } elseif ($intent->name === 'talk_to_agent') {
            $this->handleTalkToAgent($chat);
        } else {
            $this->whatsAppService->sendTextMessage($payload->userPhone, trans('whatsapp.errors.option_not_recognized'));
            $this->whatsAppService->sendClientOptions($payload->userPhone, $payload->userName, $chat->client_rfc);
        }
    }

    private function handleRequestDocumentCategoryState(Chat $chat, object $payload, object $intent): void
    {
        if ($intent->name === 'choose_doc_category') {
            $this->whatsAppService->sendTypingIndicator($payload->messageId);
            $rfc = $intent->data['rfc'];
            $category = $intent->data['category'];

            if ($chat->client_rfc !== $rfc) {
                Log::warning("Inconsistencia de RFC en el chat {$chat->id}. Chat RFC: {$chat->client_rfc}, Intent RFC: {$rfc}");
                $this->whatsAppService->sendTextMessage($payload->userPhone, trans('whatsapp.errors.security_issue'));
                $this->resetChat($chat);
                return;
            }

            $this->sendClientDocuments($chat, $category);
            // Después de enviar, volvemos a las opciones del cliente.
            $chat->update(['action' => Chat::ACTION_CLIENT_OPTIONS]);
            $this->whatsAppService->sendTextMessage($payload->userPhone, trans('whatsapp.need_anything_else'));
            //$this->whatsAppService->sendClientOptions($payload->userPhone, $payload->userName, $chat->client_rfc);

        } else {
             $this->whatsAppService->sendTextMessage($payload->userPhone, trans('whatsapp.errors.select_from_list'));
        }
    }

    private function handleTalkToAgent(Chat $chat): void
    {
        $client = Client::with('agent')->find($chat->client_id);
        if (!$client) {
            Log::error("No se pudo encontrar el cliente con ID {$chat->client_id} para la solicitud de agente.");
            $this->whatsAppService->sendTextMessage($chat->user_phone, trans('whatsapp.errors.generic_error'));
            return;
        }

        $this->whatsAppService->sendTextMessage($chat->user_phone, trans('whatsapp.agent_notification_pending'));

        // Despachar un Job para que se encargue de la notificación.
        // Esto libera el proceso del webhook inmediatamente.
        NotifyAgentJob::dispatch($client);

        $chat->update(['action' => Chat::ACTION_AWAITING_AGENT]);
    }

    private function sendClientDocuments(Chat $chat, string $category): void
    {
        $files = ClientFile::where('client_rfc', $chat->client_rfc)
            ->where('category', $category)
            ->get();

        if ($files->isEmpty()) {
            $this->whatsAppService->sendTextMessage($chat->user_phone, trans('whatsapp.errors.no_files_in_category', ['category' => $category]));
            return;
        }

        $this->whatsAppService->sendTextMessage($chat->user_phone, trans('whatsapp.sending_files', ['category' => $category]));

        $filesSentCount = 0;
        foreach ($files as $file) {
            try {
                if (!Storage::disk('public')->exists($file->file_path)) {
                    Log::error("Archivo no encontrado en storage: {$file->file_path}");
                    continue;
                }
                $fileUrl = Storage::disk('public')->url($file->file_path);

                $this->whatsAppService->sendDocument(
                    $chat->user_phone,
                    $fileUrl,
                    "Archivo de {$category}: {$file->original_file_name}",
                    $file->original_file_name
                );
                $filesSentCount++;

                usleep(1000000); // Espera 2 segundos entre envíos para evitar problemas de rate limiting
            } catch (Exception $e) {
                Log::error("Error al enviar documento {$file->id}: " . $e->getMessage());
            }
        }

        if($filesSentCount > 0) {
            $this->whatsAppService->sendTextMessage($chat->user_phone, trans('whatsapp.files_sent_summary', ['count' => $filesSentCount, 'category' => $category]));
        } else {
            $this->whatsAppService->sendTextMessage($chat->user_phone, trans('whatsapp.errors.files_not_sent', ['category' => $category]));
        }
    }

    public function handleIaConversationState(Chat $chat, object $payload): void
    {
        // Verificar si el chat ya está en conversación con IA
        $this->whatsAppService->sendTypingIndicator($payload->messageId);

        // Continuar conversación con IA
        $botResponse = $this->geminiService->chatWithIA($payload->userMessage, $payload->userName, $chat->context);
        $this->whatsAppService->sendTextMessage($payload->userPhone, $botResponse); //. "\n\n_(Escribe 'salir' para volver al menú)_"
        // El estado sigue siendo ACTION_IA_CONVERSATION
    }
}
