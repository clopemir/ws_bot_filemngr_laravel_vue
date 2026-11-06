<?php

namespace App\Http\Controllers\WhatsApp;

use App\Exceptions\WhatsAppApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\WhatsAppWebhookRequest;
use App\Models\Agent;
use App\Models\Chat;
use App\Services\ChatStateService;
use App\Services\AgentRequestHandler;
use App\Services\WhatsApp\IntentParser;
use App\Services\WhatsApp\PayloadParser;
use App\Services\WhatsAppService;
use App\Support\Mask;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class WaController extends Controller
{
    public function __construct(
        private WhatsAppService $whatsAppService,
        private ChatStateService $chatStateService,
        private AgentRequestHandler $agentRequestHandler
    ) {
    }

    /**
     * Verifica el webhook de WhatsApp (handshake inicial de Meta).
     */
    public function verifyWebhook(Request $request)
    {
        $verifyToken = (string) config('services.whatsapp.verify_token');

        if ($verifyToken !== ''
            && $request->query('hub_mode') === 'subscribe'
            && hash_equals($verifyToken, (string) $request->query('hub_verify_token'))) {
            Log::info('WhatsApp Webhook Verified.');
            return response((string) $request->query('hub_challenge'), 200);
        }

        Log::warning('WhatsApp Webhook verification failed.', ['ip' => $request->ip()]);
        return response('Forbidden', 403);
    }

    /**
     * Recibe y procesa los mensajes entrantes de WhatsApp.
     * La firma de Meta ya fue validada por el middleware VerifyWhatsAppSignature.
     */
    public function receiveMessage(WhatsAppWebhookRequest $request): JsonResponse
    {
        $payload = null;

        try {
            $payload = PayloadParser::parse($request);
            if (!$payload) {
                return response()->json(['status' => 'ok', 'message' => 'Unsupported message type']);
            }

            // Meta reintenta los webhooks que tardan o fallan: cada mensaje se procesa una sola vez.
            if (!Cache::add("wa-message:{$payload->messageId}", true, now()->addDay())) {
                return response()->json(['status' => 'ok', 'message' => 'Duplicate']);
            }

            $this->whatsAppService->markMessageAsRead($payload->messageId);

            $intent = IntentParser::parse($payload->userMessage, $payload->messageType);
            $chat = $this->findOrCreateChat($payload);
            $agent = $this->findAgent($payload->userPhone);

            if ($agent) {
                $this->agentRequestHandler->handle($agent, $payload->userMessage, $chat, $payload);
                $chat->addMessageToContext($payload->userMessage, 'user');
                return response()->json(['status' => 'ok', 'action' => $chat->action]);
            }

            if ($chat->wasRecentlyCreated) {
                $this->whatsAppService->sendInitialButtons($chat->user_phone, $chat->user_name);
                return response()->json(['status' => 'ok', 'action' => $chat->action]);
            }

            // No guardamos en el historial datos de verificación (RFC o códigos).
            $isSensitive = in_array($chat->action, [Chat::ACTION_REQUEST_RFC, Chat::ACTION_REQUEST_OTP], true);
            $chat->addMessageToContext($isSensitive ? '[dato de verificación]' : $payload->userMessage, 'user');

            if (in_array($intent->name, ['reset', 'end_conversation'], true)) {
                $this->chatStateService->resetChat($chat);
                return response()->json(['status' => 'ok', 'action' => $intent->name]);
            }

            $this->chatStateService->handleState($chat, $payload, $intent);

        } catch (WhatsAppApiException $e) {
            // Si falla una llamada a la API de WhatsApp (ej. enviar un mensaje), lo registramos.
            Log::error("WhatsApp API Exception: {$e->getMessage()}", [
                'phone' => Mask::phone($payload->userPhone ?? null),
            ]);
        } catch (Throwable $e) {
            Log::critical("Error fatal al procesar el mensaje de WhatsApp: {$e->getMessage()}", [
                'phone' => Mask::phone($payload->userPhone ?? null),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        // Siempre responder 200 OK a WhatsApp para evitar que reenvíe el webhook.
        return response()->json(['status' => 'ok']);
    }

    /**
     * Encuentra un chat existente o crea uno nuevo.
     */
    private function findOrCreateChat(object $payload): Chat
    {
        return Chat::firstOrCreate(
            ['wa_id' => $payload->waId],
            [
                'user_name' => $payload->userName,
                'user_phone' => $payload->userPhone,
                'client_rfc' => null,
                'client_id' => null,
                'context' => [['role' => 'user', 'content' => $payload->userMessage, 'timestamp' => now()->toIso8601String()]],
                'user_intention' => '',
                'action' => Chat::ACTION_BASE,
                'is_client' => false
            ]
        );
    }

    private function findAgent(string $phone): ?Agent
    {
        $lastDigits = substr(PhoneNumber::digits($phone), -10);

        // Un agente dado de baja ya no tiene acceso a los documentos de los clientes.
        return Agent::where('agent_status', true)
            ->where('agent_phone', 'like', "%{$lastDigits}")
            ->get()
            ->first(fn (Agent $agent) => PhoneNumber::matches($agent->agent_phone, $phone));
    }
}
