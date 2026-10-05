<?php

namespace App\Services;

use App\Jobs\NotifyAgentJob;
use App\Jobs\NotifySecurityEventJob;
use App\Jobs\SendClientDocumentsJob;
use App\Models\Chat;
use App\Models\Client;
use App\Models\File as ClientFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatStateService
{
    /**
     * Estados que solo son accesibles con una sesión de cliente verificada y vigente.
     */
    private const PROTECTED_ACTIONS = [
        Chat::ACTION_CLIENT_OPTIONS,
        Chat::ACTION_REQUEST_DOCUMENT_CATEGORY,
        Chat::ACTION_AWAITING_AGENT,
    ];

    public function __construct(
        private WhatsAppService $whatsAppService,
        private ClientService $clientService,
        private ClientVerificationService $verificationService,
        private GeminiService $geminiService
    ) {
    }

    /**
     * Maneja el estado actual del chat basado en la acción y la intención.
     */
    public function handleState(Chat $chat, object $payload, object $intent): void
    {
        if (in_array($chat->action, self::PROTECTED_ACTIONS, true) && !$chat->hasValidSession()) {
            $this->expireSession($chat);
            return;
        }

        switch ($chat->action) {
            case Chat::ACTION_BASE:
                $this->handleBaseState($chat, $payload, $intent);
                break;
            case Chat::ACTION_REQUEST_RFC:
                $this->handleRfcRequestState($chat, $payload, $intent);
                break;
            case Chat::ACTION_REQUEST_OTP:
                $this->handleOtpState($chat, $payload, $intent);
                break;
            case Chat::ACTION_CLIENT_OPTIONS:
                $this->handleClientOptionsState($chat, $payload, $intent);
                break;
            case Chat::ACTION_REQUEST_DOCUMENT_CATEGORY:
                $this->handleRequestDocumentCategoryState($chat, $payload, $intent);
                break;
            case Chat::ACTION_AWAITING_AGENT:
                $this->handleAwaitingAgentState($chat, $payload, $intent);
                break;
            default:
                Log::warning("Acción de chat desconocida: {$chat->action} para el chat ID: {$chat->id}");
                $this->resetChat($chat);
                break;
        }
    }

    /**
     * Resetea el chat al estado inicial, cierra la sesión y se despide.
     */
    public function resetChat(Chat $chat): void
    {
        $chat->clearSession();

        $this->whatsAppService->sendTextMessage($chat->user_phone, trans('whatsapp.goodbye', ['name' => $chat->user_name]));
    }

    // --- MANEJADORES DE ESTADO ---

    private function handleBaseState(Chat $chat, object $payload, object $intent): void
    {
        $this->whatsAppService->sendTypingIndicator($payload->messageId);

        if ($intent->name === 'is_client') {
            if ($chat->hasValidSession()) {
                $chat->update(['action' => Chat::ACTION_CLIENT_OPTIONS]);
                $this->whatsAppService->sendClientOptions($payload->userPhone, $chat->client->client_name);
                return;
            }

            $chat->update(['action' => Chat::ACTION_REQUEST_RFC]);
            $this->whatsAppService->sendTextMessage($payload->userPhone, trans('whatsapp.request_rfc'));
        } elseif ($intent->name === 'is_not_client') {
            $this->whatsAppService->sendInfo($payload->userPhone, $payload->userName);
            $this->resetChat($chat);
        } else {
            $this->whatsAppService->sendInitialButtons($payload->userPhone, $payload->userName);
        }
    }

    /**
     * Factor 1: número registrado + RFC. Si el número no está registrado, se pasa al factor 2.
     */
    private function handleRfcRequestState(Chat $chat, object $payload, object $intent): void
    {
        $this->whatsAppService->sendTypingIndicator($payload->messageId);
        $phone = $payload->userPhone;

        if ($intent->name !== 'text_input') {
            $this->whatsAppService->sendTextMessage($phone, trans('whatsapp.request_rfc'));
            return;
        }

        if ($this->verificationService->isLockedOut($phone)) {
            $this->whatsAppService->sendTextMessage($phone, trans('whatsapp.errors.too_many_attempts', [
                'minutes' => $this->verificationService->lockoutMinutes($phone),
            ]));
            $chat->clearSession();
            return;
        }

        $rfc = Str::upper(Str::squish($payload->userMessage));

        if (!$this->clientService->isValidRfcFormat($rfc)) {
            $this->whatsAppService->sendTextMessage($phone, trans('whatsapp.errors.invalid_rfc'));
            return; // Mantenemos el estado para que reintente.
        }

        // Cada búsqueda cuenta como intento; el contador se limpia al verificar con éxito.
        $this->verificationService->registerRfcAttempt($phone);

        $client = $this->clientService->getClientByRfc($rfc);

        // Factor 1 cumplido: el número que escribe es el registrado para ese RFC.
        if ($client && $client->ownsPhone($phone)) {
            $this->verificationService->grantSession($chat, $client, Chat::VERIFIED_BY_PHONE);
            $this->whatsAppService->sendClientOptions($phone, $client->client_name);
            return;
        }

        // Un cliente registrado puede recibir el detalle de "RFC no encontrado" (p. ej. un error al teclear).
        if (!$client && $this->clientService->isRegisteredPhone($phone)) {
            $this->whatsAppService->sendTextMessage($phone, trans('whatsapp.errors.rfc_not_found', ['rfc' => $rfc]));
            return;
        }

        // Número NO registrado: factor 2. La respuesta es idéntica exista o no el RFC,
        // para que nadie pueda usar el bot para averiguar quién es cliente.
        if ($client) {
            $this->verificationService->issueCode($chat, $client);
            NotifySecurityEventJob::dispatchAfterResponse($client, NotifySecurityEventJob::UNREGISTERED_PHONE, $phone);
        }

        $chat->update(['action' => Chat::ACTION_REQUEST_OTP]);
        $this->whatsAppService->sendTextMessage($phone, trans('whatsapp.otp.sent', [
            'minutes' => (int) config('whatsapp_bot.security.otp_ttl_minutes', 10),
        ]));
    }

    /**
     * Factor 2: el solicitante escribe el código que el titular recibió en sus medios registrados.
     */
    private function handleOtpState(Chat $chat, object $payload, object $intent): void
    {
        $this->whatsAppService->sendTypingIndicator($payload->messageId);
        $phone = $payload->userPhone;

        if ($intent->name !== 'text_input') {
            $this->whatsAppService->sendTextMessage($phone, trans('whatsapp.otp.prompt'));
            return;
        }

        $result = $this->verificationService->verifyCode($chat, $payload->userMessage);

        switch ($result['status']) {
            case ClientVerificationService::CODE_VERIFIED:
                $this->whatsAppService->sendTextMessage($phone, trans('whatsapp.otp.verified'));
                $this->whatsAppService->sendClientOptions($phone, $result['client']->client_name);
                NotifySecurityEventJob::dispatchAfterResponse($result['client'], NotifySecurityEventJob::OTP_VERIFIED, $phone);
                break;

            case ClientVerificationService::CODE_INVALID:
                $this->whatsAppService->sendTextMessage($phone, trans('whatsapp.otp.invalid', ['remaining' => $result['remaining']]));
                break;

            case ClientVerificationService::CODE_LOCKED:
                $chat->clearSession();
                $this->whatsAppService->sendTextMessage($phone, trans('whatsapp.otp.locked'));
                if ($result['client'] ?? null) {
                    NotifySecurityEventJob::dispatchAfterResponse($result['client'], NotifySecurityEventJob::OTP_LOCKED, $phone);
                }
                break;

            default: // Vencido o inexistente
                $chat->clearSession();
                $this->whatsAppService->sendTextMessage($phone, trans('whatsapp.otp.expired'));
                break;
        }
    }

    private function handleClientOptionsState(Chat $chat, object $payload, object $intent): void
    {
        $client = $chat->client;

        if ($intent->name === 'ask_doc_categories') {
            $this->whatsAppService->sendTypingIndicator($payload->messageId);

            $categories = $this->categoriesFor($client);

            if (empty($categories)) {
                $this->whatsAppService->sendTextMessage($payload->userPhone, trans('whatsapp.errors.no_categories_found'));
                $this->whatsAppService->sendClientOptions($payload->userPhone, $client->client_name);
                return;
            }

            $chat->update(['action' => Chat::ACTION_REQUEST_DOCUMENT_CATEGORY]);
            $this->whatsAppService->sendDocumentCategoryOptions($payload->userPhone, $client->client_name, $categories);

        } elseif ($intent->name === 'talk_to_agent') {
            $this->handleTalkToAgent($chat);
        } else {
            $this->whatsAppService->sendTextMessage($payload->userPhone, trans('whatsapp.errors.option_not_recognized'));
            $this->whatsAppService->sendClientOptions($payload->userPhone, $client->client_name);
        }
    }

    private function handleRequestDocumentCategoryState(Chat $chat, object $payload, object $intent): void
    {
        // Si el usuario toca una opción del menú anterior, la atendemos normalmente.
        if (in_array($intent->name, ['ask_doc_categories', 'talk_to_agent'], true)) {
            $this->handleClientOptionsState($chat, $payload, $intent);
            return;
        }

        if ($intent->name !== 'choose_doc_category') {
            $this->whatsAppService->sendTextMessage($payload->userPhone, trans('whatsapp.errors.select_from_list'));
            return;
        }

        $this->whatsAppService->sendTypingIndicator($payload->messageId);

        // La categoría se resuelve SOLO entre las del cliente verificado en la sesión.
        $category = collect($this->categoriesFor($chat->client))
            ->first(fn (string $category) => WhatsAppService::categoryOptionId($category) === $intent->data['option_id']);

        if (!$category) {
            $this->whatsAppService->sendTextMessage($payload->userPhone, trans('whatsapp.errors.select_from_list'));
            return;
        }

        $this->whatsAppService->sendTextMessage($payload->userPhone, trans('whatsapp.sending_files', ['category' => $category]));
        $chat->update(['action' => Chat::ACTION_CLIENT_OPTIONS]);

        // Se ejecuta después de responder 200 a Meta (sin reintentos por demora) y sin depender
        // de que haya un worker de colas corriendo en el servidor.
        SendClientDocumentsJob::dispatchAfterResponse($chat->id, $chat->client_id, $category);
    }

    private function handleAwaitingAgentState(Chat $chat, object $payload, object $intent): void
    {
        $chat->update(['action' => Chat::ACTION_CLIENT_OPTIONS]);

        if ($intent->name === 'text_input') {
            $this->whatsAppService->sendTextMessage($payload->userPhone, trans('whatsapp.agent_already_notified'));
            $this->whatsAppService->sendClientOptions($payload->userPhone, $chat->client->client_name);
            return;
        }

        $this->handleClientOptionsState($chat, $payload, $intent);
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

        // Se notifica con el número que realmente escribió (puede ser un tercero verificado por código).
        NotifyAgentJob::dispatch($client, $chat->user_phone);

        $chat->update(['action' => Chat::ACTION_AWAITING_AGENT]);
    }

    private function expireSession(Chat $chat): void
    {
        $chat->clearSession(Chat::ACTION_REQUEST_RFC);
        $this->whatsAppService->sendTextMessage($chat->user_phone, trans('whatsapp.errors.session_expired'));
    }

    /**
     * @return string[]
     */
    private function categoriesFor(Client $client): array
    {
        return ClientFile::where('client_rfc', $client->client_rfc)
            ->whereNotNull('category')->where('category', '!=', '')
            ->distinct()->orderBy('category')->pluck('category')->all();
    }

    /**
     * Conversación con IA (solo disponible para agentes).
     */
    public function handleIaConversationState(Chat $chat, object $payload): void
    {
        $this->whatsAppService->sendTypingIndicator($payload->messageId);

        $botResponse = $this->geminiService->chatWithIA(str_replace(["\n", "\r"], ' ', $payload->userMessage), $payload->userName, $chat->context ?? []);
        $this->whatsAppService->sendTextMessage($payload->userPhone, $botResponse);
    }
}
