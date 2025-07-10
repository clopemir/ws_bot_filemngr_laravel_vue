<?php

namespace App\Http\Controllers\WhatsApp;

use Exception;
use Carbon\Carbon;
use App\Models\Chat;
use App\Models\Agent;
use App\Models\Client;
use App\Jobs\NotifyAgentJob;
use Illuminate\Http\Request;
use App\Services\WhatsAppService;
use App\Services\ChatStateService;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Services\WhatsApp\IntentParser;
use App\Exceptions\WhatsAppApiException;
use App\Services\WhatsApp\PayloadParser;
use App\Http\Requests\WhatsAppWebhookRequest;

class WaController extends Controller
{
    public function __construct(
        private WhatsAppService $whatsAppService,
        private ChatStateService $chatStateService
    ) {
        $this->whatsAppService = $whatsAppService;
        $this->chatStateService = $chatStateService;
    }

    /**
     * Verifica el webhook de WhatsApp.
     * La validación del token ahora está en un Form Request.
     */
    public function verifyWebhook(Request $request)
    {
        $verifyToken = config('services.whatsapp.verify_token');
        if ($request->hub_mode === 'subscribe' && $request->hub_verify_token === $verifyToken) {
            Log::info('WhatsApp Webhook Verified.');
            return response($request->hub_challenge, 200);
        }
        Log::warning('WhatsApp Webhook verification failed.', $request->all());
        return response('Forbidden', 403);
    }

    /**
     * Recibe y procesa los mensajes entrantes de WhatsApp.
     * Usa un Form Request para la validación inicial del payload.
     */
    public function receiveMessage(WhatsAppWebhookRequest $request)
    {
        try {
            $payload = PayloadParser::parse($request);
            if (!$payload) {
                return response()->json(['status' => 'ok', 'message' => 'Unsupported message type']);
            }

            // Marcar mensaje como leído inmediatamente.
            $this->whatsAppService->markMessageAsRead($payload->messageId);

            $intent = IntentParser::parse($payload->userMessage);

            $chat = $this->findOrCreateChat($payload);

            if (!$chat) {
                throw new \RuntimeException("No se pudo crear o encontrar el chat para el usuario.");
            }

            $chat->addMessageToContext($payload->userMessage, 'user');

            if (Agent::where('agent_phone', $chat->user_phone)->exists()) {
                $chat->update(['action' => Chat::INTENT_IA_CHAT]);
                $this->chatStateService->handleState($chat, $payload, (object)['name' => 'ia_chat']);
                return response()->json(['status' => 'ok', 'action' => 'agent']);
            }

            // Si el usuario quiere cancelar, reseteamos el chat.
            if ($intent->name === 'reset') {
                $this->chatStateService->resetChat($chat);
                return response()->json(['status' => 'ok', 'action' => 'chat_reset']);
            }

            if ($intent->name === 'end_conversation') {
                // si el usuario se despide, simplemente terminamos la conversación. enviando un mensaje y reseteando el estado base
                $this->chatStateService->resetChat($chat);
                return response()->json(['status' => 'ok', 'action' => 'conversation_ended']);
            }

            // Delegamos la lógica de qué hacer a un servicio especializado.
            $this->chatStateService->handleState($chat, $payload, $intent);

            return response()->json(['status' => 'ok', 'action' => $chat->action]);

        } catch (WhatsAppApiException $e) {
            // Si falla una llamada a la API de WhatsApp (ej. enviar un mensaje), lo logueamos.
            // No se puede hacer mucho más que loguear, ya que ya estamos en un proceso de webhook.
            Log::error("WhatsApp API Exception: {$e->getMessage()}", [
                'wa_id' => $payload->waId ?? 'N/A',
            ]);
        } catch (Exception $e) {
            // Captura cualquier otra excepción inesperada.
            Log::critical("Error fatal al procesar el mensaje de WhatsApp: {$e->getMessage()}", [
                'wa_id' => $payload->waId ?? 'N/A',
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            // Siempre responder 200 OK a WhatsApp para evitar que reenvíe el webhook.
            return response()->json(['status' => 'ok']);
        }
    }

    /**
     * Encuentra un chat existente o crea uno nuevo.
     */
    private function findOrCreateChat(object $payload): Chat
    {

        $chat = Chat::firstOrCreate(
            ['wa_id' => $payload->waId],
            [
                'user_name' => $payload->userName,
                'user_phone' => $payload->userPhone,
                'client_rfc' => '',
                'client_id' => null,
                'context' => [['role' => 'user', 'content' => $payload->userMessage, 'timestamp' => now()->toIso8601String()]],
                'user_intention' => '',
                'action' => Chat::ACTION_BASE,
                'is_client' => false
            ]
        );

        // Si el chat es nuevo, le damos la bienvenida.
        if ($chat->wasRecentlyCreated) {
            $this->whatsAppService->sendInitialButtons($chat->user_phone, $chat->user_name);
        }

        return $chat;
    }
}
