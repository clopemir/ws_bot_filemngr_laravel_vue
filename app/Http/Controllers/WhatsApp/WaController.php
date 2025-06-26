<?php

namespace App\Http\Controllers\WhatsApp;

use Exception;
use Carbon\Carbon;
use App\Models\Chat;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Services\ClientService;
use App\Services\GeminiService;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Models\Client; // Asegúrate que el namespace sea correcto
use App\Models\Agent;   // Asegúrate que el namespace sea correcto
use App\Models\File as ClientFile;

class WaController extends Controller
{
    private WhatsAppService $whatsAppService;
    private GeminiService $geminiService;
    private ClientService $clientService;

    // Constantes para acciones y mensajes clave
    private const ACTION_BASE = 'base';
    private const ACTION_REQUEST_RFC = 'request_rfc';
    private const ACTION_VALIDATE_CLIENT_OPTIONS = 'validate_client_options';
    private const ACTION_REQUEST_DOCUMENT_CATEGORY = 'request_document_category'; // Nuevo estado
    private const ACTION_DOWNLOAD_DOCS_CONFIRMED = 'download_docs_confirmed';
    private const ACTION_AWAITING_AGENT = 'awaiting_agent';
    private const ACTION_IA_CONVERSATION = 'ia_conversation';

    private const INTENT_CLIENT = 'client';
    private const INTENT_NO_CLIENT = 'no_client';
    public const INTENT_TALK_TO_AGENT = 'agent_chat'; // ID del botón/lista para hablar con agente
    private const INTENT_DOWNLOAD_DOCS_PREFIX = 'dld_'; // Prefijo para IDs de descarga de documentos
    private const INTENT_IA_CHAT = 'ia_chat';


    //Nuevos estados para categorias

    public const INTENT_ASK_DOC_CATEGORIES_PREFIX = 'ask_doc_cat_'; // Payload: ask_doc_cat_{RFC}
    public const INTENT_CHOOSE_DOC_CATEGORY_PREFIX = 'cho_doc_cat_'; // Payload: cho_doc_cat_{CATEGORY}_{RFC}


    public function __construct(
        WhatsAppService $whatsAppService,
        GeminiService $geminiService,
        ClientService $clientService
    ) {
        $this->whatsAppService = $whatsAppService;
        $this->geminiService = $geminiService;
        $this->clientService = $clientService;
    }

    /**
     * Verifica el webhook de WhatsApp.
     */
    public function verifyWebhook(Request $request)
    {
        $verifyToken = config('services.whatsapp.verify_token');
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode && $token) {
            if ($mode === 'subscribe' && $token === $verifyToken) {
                Log::info('WhatsApp Webhook Verified.');
                return response($challenge, 200);
            }
            Log::warning('WhatsApp Webhook verification failed. Mode or Token mismatch.', $request->all());
            return response('Forbidden', 403);
        }
        Log::warning('WhatsApp Webhook verification failed. Missing mode or token.', $request->all());
        return response('Invalid Request', 400);
    }

    /**
     * Recibe y procesa los mensajes entrantes de WhatsApp.
     */
    public function receiveMessage(Request $request)
    {


        if (!$request->has('entry.0.changes.0.value.messages.0')) {
            Log::warning('Empty or invalid request structure received.', $request->all());
            return response()->json(['message' => 'INVALID_REQUEST_STRUCTURE'], 400);
        }

        try {

            $payload = $this->parseIncomingMessage($request);

            if (!$payload) {
                return response()->json(['message' => 'INVALID_PAYLOAD_PARSING'], 400);
            }

            // Marcar mensaje como leído (siempre al inicio)
            $this->whatsAppService->markMessageAsRead($payload['message_id']);

            $chat = $this->findOrCreateChat($payload);


            // if($chat->wasRecentlyCreated) {

            //     $this->whatsAppService->sendInitialButtons($payload['user_phone'], $payload['user_name']);
            //     return response()->json(['message' => 'EVENT_RECEIVED_NEW_CHAT'], 200);

            // }

            // if ($chat->user_phone == Agent::whereAgentPhone()) {

            //     $this->handleIaConversationState($chat, $payload);
            //     $chat->update(['action' => self::ACTION_IA_CONVERSATION]);
            //     //return response()->json(['message' => 'EVENT_RECEIVED_NEW_CHAT'], 200);
            // }

            // Si el chat fue recién creado
            if ($chat->wasRecentlyCreated) {
                // Verificar si el user_phone pertenece a un agente
                $isAgent = Agent::where('agent_phone', $chat->user_phone)->exists();

                if ($isAgent) {
                    // Si es un agente, ir directo al flujo de IA
                    $this->handleIaConversationState($chat, $payload);
                    $chat->update(['action' => self::ACTION_IA_CONVERSATION]);

                    return response()->json(['message' => 'EVENT_RECEIVED_AGENT_CHAT'], 200);
                }

                // Si no es un agente, enviar botones iniciales al cliente
                $this->whatsAppService->sendInitialButtons($payload['user_phone'], $payload['user_name']);
                return response()->json(['message' => 'EVENT_RECEIVED_NEW_CHAT'], 200);
            }

            // Actualizar contexto e intención para chats existentes
            $newContext = array_merge((array)$chat->context, [['role' => 'user', 'content' => $payload['user_message']]]);
            // Limitar el tamaño del contexto si es necesario para no exceder límites de BD o API
            if (count($newContext) > 20) { // Ejemplo: mantener últimos 20 intercambios
                $newContext = array_slice($newContext, -20);
            }

            $chat->update([
                'context' => $newContext,
                'user_intention' => $this->geminiService->detectIntent($payload['user_message'], array_column($newContext, 'content')), // Pasar historial para mejor detección
                'updated_at' => Carbon::now() // Forzar actualización para lógica de inactividad
            ]);

            // Lógica para "Hablar con un agente" (puede ser activada desde varias opciones)
            if ($payload['user_message'] === self::INTENT_TALK_TO_AGENT) {
                $this->handleTalkToAgent($chat, $payload);
                return response()->json(['message' => 'EVENT_RECEIVED_AGENT_REQUEST'], 200);
            }

            // Máquina de estados simplificada
            switch ($chat->action) {
                case self::ACTION_BASE:
                    $this->handleBaseState($chat, $payload);
                    break;
                case 'req_info':
                    $this->whatsAppService->sendTextMessage($payload['user_phone'], "Gracias por comunicarte con Nosotros!\n\nHasta Pronto.");
                    $this->resetChatToActionBase($chat, $payload);
                    break;
                case self::ACTION_REQUEST_RFC:
                    $this->handleRfcRequestState($chat, $payload);
                    break;
                case self::ACTION_VALIDATE_CLIENT_OPTIONS:
                    $this->handleClientOptionsState($chat, $payload);
                    break;
                    //Nuevo para categorias
                case self::ACTION_REQUEST_DOCUMENT_CATEGORY: // Nuevo case
                    $this->handleRequestDocumentCategoryState($chat, $payload);
                    break;
                    //
                case self::ACTION_DOWNLOAD_DOCS_CONFIRMED:
                    $this->handleDownloadConfirmedState($chat, $payload);
                    break;
                case self::ACTION_IA_CONVERSATION:
                    $this->handleIaConversationState($chat, $payload);
                    break;
                case self::ACTION_AWAITING_AGENT:
                    // El usuario ya está esperando un agente, quizás enviar un mensaje de recordatorio o nada.
                    $this->whatsAppService->sendTextMessage($payload['user_phone'], "Ya hemos notificado a un agente. Se comunicarán contigo pronto. Si deseas, puedes escribir '*cancelar*' para volver al menú principal.");
                    if (Str::lower($payload['user_message']) === 'cancelar') {
                        $this->resetChatToActionBase($chat, $payload);
                    }
                    break;
                default:
                    Log::warning("Unknown chat action: {$chat->action} for chat ID: {$chat->id}");
                    $this->resetChatToActionBase($chat, $payload);
                    break;
            }

            return response()->json(['message' => 'EVENT_RECEIVED'], 200);

        } catch (Exception $e) {
            Log::error("Error processing WhatsApp message: {$e->getMessage()}\nStack: {$e->getTraceAsString()}", $request->all());
            // No enviar respuesta JSON aquí si ya se envió una, o podría causar error.
            // WhatsApp espera un 200 OK rápido. Los errores se manejan internamente.
            // Si la excepción ocurre antes de enviar cualquier respuesta, entonces sí:
            if (!headers_sent()) {
                 return response()->json(['message' => 'INTERNAL_SERVER_ERROR'], 500);
            }
            // Si ya se envió una respuesta, solo loguear.
        }
        return response()->json(['message' => 'EVENT_RECEIVED_FALLBACK'], 200); // Fallback por si acaso
    }

    private function parseIncomingMessage(Request $request): ?array
    {
        $value = $request->input('entry.0.changes.0.value');
        if (!$value || !isset($value['messages'][0]) || !isset($value['contacts'][0])) {
            Log::warning('Essential data missing in webhook payload for parsing.', ['value' => $value]);
            return null;
        }

        $messageData = $value['messages'][0];
        $contactData = $value['contacts'][0];

        //limpieza de formato de numero regional
        $userPhone = $messageData['from'];
        if (Str::startsWith($userPhone, '521')) {
            $userPhone = '52' . substr($userPhone, 3);
        }

        $parsed = [
            'wa_id' => $contactData['wa_id'],
            'user_name' => $contactData['profile']['name'] ?? 'Usuario',
            'user_phone' => $userPhone,
            'message_id' => $messageData['id'],
            'message_type' => $messageData['type'],
            'user_message' => '',
        ];

        switch ($parsed['message_type']) {
            case 'interactive':
                $interactive = $messageData['interactive'];
                if (isset($interactive['button_reply']['id'])) {
                    $parsed['user_message'] = $interactive['button_reply']['id'];
                } elseif (isset($interactive['list_reply']['id'])) {
                    $parsed['user_message'] = $interactive['list_reply']['id'];
                } else {
                    Log::warning('Interactive message without known reply type.', ['interactive' => $interactive]);
                    return null;
                }
                break;
            case 'text':
                $parsed['user_message'] = $messageData['text']['body'] ?? '';
                if (empty($parsed['user_message'])) {
                     Log::warning('Text message with empty body.', ['messageData' => $messageData]);
                     return null; // O manejar como mensaje vacío
                }
                break;
            default:
                Log::info("Unsupported message type received: {$parsed['message_type']}", ['messageData' => $messageData]);
                $this->whatsAppService->sendTextMessage($parsed['user_phone'], "Lo lamento, no he entendido la intención de tu mensaje, prueba nuevamente con las opciones disponibles:");

                        //$chat->update(['action' => self::ACTION_BASE]);
                        $this->whatsAppService->sendInitialButtons($parsed['user_phone'], $parsed['user_name']);

                return null;
        }
        return $parsed;
    }

    private function findOrCreateChat(array $payload): Chat
    {
        return Chat::firstOrCreate(
            ['wa_id' => $payload['wa_id']],
            [
                'user_name' => $payload['user_name'],
                'user_phone' => $payload['user_phone'],
                'client_rfc' => '',
                'client_id' => null,
                'context' => [['role' => 'user', 'content' => $payload['user_message']]],
                'user_intention' => $this->geminiService->detectIntent($payload['user_message']),
                'action' => self::ACTION_BASE, // Estado inicial
                'is_client' => false, // Por defecto no es cliente
            ]
        );
    }

    //resetear estado original del chat:

    private function resetChatToActionBase(Chat $chat, array $payload): void
    {
        $chat->update(['action' => self::ACTION_BASE]);
        $this->whatsAppService->sendInitialButtons($payload['user_phone'], $payload['user_name']);
    }

    // --- MANEJADORES DE ESTADO ---
    private function handleBaseState(Chat $chat, array $payload): void
    {
        switch ($payload['user_message']) {
            case self::INTENT_CLIENT:
                $this->whatsAppService->sendTextMessage($payload['user_phone'], "Para continuar, por favor escribe tu RFC (ej: XAXX010101000).");
                $chat->update(['action' => self::ACTION_REQUEST_RFC]);
                break;
            case self::INTENT_NO_CLIENT:
                $this->whatsAppService->sendInfo($payload['user_phone'], $payload['user_name']);
                $chat->update(['action' => 'req_info']);
                break;
            default:
                // Intento de chat general con IA si la intención es 'chatbot' o 'pregunta_general'
                if (in_array($chat->user_intention, ['chatbot', 'pregunta_general', 'pregunta_general_fiscal'])) {
                    $botResponse = $this->geminiService->chatWithIA($payload['user_message'], $payload['user_name'], (array)$chat->context);
                    $this->whatsAppService->sendTextMessage($payload['user_phone'], $botResponse);
                } else {
                    // Si no es una opción clara ni chat, reenviar opciones iniciales
                    $this->whatsAppService->sendInitialButtons($payload['user_phone'], $payload['user_name']);
                }
                break;
        }
    }

    private function handleRfcRequestState(Chat $chat, array $payload): void
    {
        $rfc = Str::lower(Str::squish($payload['user_message'])); // Limpiar y a mayúsculas

        if ($this->clientService->isValidRfcFormat($rfc)) {

            $client = $this->clientService->getClientByRfc($rfc);
            // Or this $client = Clients::where('client_rfc', $rfc)->first();

            if ($client) {
                // Cliente encontrado
                $chat->update([
                    'is_client' => true,
                    'client_rfc' => $rfc, // Guardar RFC validado
                    'client_id' => $client->id, // Enlazar con el modelo Client si existe
                    'action' => self::ACTION_VALIDATE_CLIENT_OPTIONS,
                ]);

                if (empty($client->wa_id) && !empty($payload['wa_id'])) {
                    $client->wa_id = $payload['wa_id'];
                    $client->save();
                }

                $this->whatsAppService->sendClientOptions($payload['user_phone'], $client->name ?? $payload['user_name'], $rfc);
            } else {
                // RFC válido pero no encontrado como cliente
                $this->whatsAppService->sendTextMessage($payload['user_phone'], "El RFC '{$rfc}' no se encuentra en nuestros registros de clientes. \n\n Intenta de nuevo o selecciona 'Aún no soy cliente'.");
                $this->resetChatToActionBase($chat, $payload);
            }
        } else {
            $this->whatsAppService->sendTextMessage($payload['user_phone'], "El formato del RFC no es válido. Por favor, inténtalo de nuevo \n (ej: XAXX010101000).");
            // Mantener el estado ACTION_REQUEST_RFC para reintentar
        }
    }

    private function handleClientOptionsState(Chat $chat, array $payload): void
    {
        $userMessage = $payload['user_message'];

        // if (Str::startsWith($userMessage, self::INTENT_DOWNLOAD_DOCS_PREFIX)) {

        //     $rfcFromIntent = Str::after($userMessage, self::INTENT_DOWNLOAD_DOCS_PREFIX);
        //     Log::info("rfc chat: {$chat->client_rfc}, y rfc respuesta: {$rfcFromIntent}");
        //     if (Str::lower($chat->client_rfc) === Str::lower($rfcFromIntent)) { // Doble verificación

        //         $filesSent = $this->sendClientDocuments($payload['user_phone'], $payload['user_name'], Str::lower($chat->client_rfc));
        //         if ($filesSent) {
        //             $this->whatsAppService->sendTextMessage($payload['user_phone'], "Se han enviado los documentos encontrados para {$chat->client_rfc}. ¿Necesitas algo más?");
        //             $chat->update(['action' => self::ACTION_DOWNLOAD_DOCS_CONFIRMED]); // Nuevo estado para seguimiento
        //         } else {
        //             // El método sendClientDocuments ya envía un mensaje si no hay archivos.
        //             // Regresar a las opciones de cliente.
        //             $this->whatsAppService->sendClientOptions($payload['user_phone'], $payload['user_name'], $chat->client_rfc);
        //         }
        //     } else {
        //         Log::warning("RFC mismatch in download intent. Chat RFC: {$chat->client_rfc}, Intent RFC: {$rfcFromIntent}");
        //         $this->whatsAppService->sendTextMessage($payload['user_phone'], "Hubo un problema de seguridad. Por favor, reinicia la conversación.");
        //         $chat->update(['action' => self::ACTION_BASE]);
        //         $this->whatsAppService->sendInitialButtons($payload['user_phone'], $payload['user_name']);
        //     }
        // }
        if (Str::startsWith($userMessage, self::INTENT_ASK_DOC_CATEGORIES_PREFIX)) {
            $rfcFromIntent = Str::after($userMessage, self::INTENT_ASK_DOC_CATEGORIES_PREFIX);
            if ($chat->client_rfc === $rfcFromIntent) {
                $categories = ClientFile::where('client_rfc', $rfcFromIntent)
                                          ->whereNotNull('category') // Asegurar que category no sea null
                                          ->where('category', '!=', '') // Asegurar que category no sea vacío
                                          ->distinct()
                                          ->orderBy('category') // Opcional: ordenar categorías
                                          ->pluck('category')
                                          ->all();

                if (empty($categories)) {
                    $this->whatsAppService->sendTextMessage($payload['user_phone'], "No hay categorías de documentos disponibles para el RFC {$rfcFromIntent} en este momento.");
                    // Devolver a opciones de cliente
                    $this->whatsAppService->sendClientOptions($payload['user_phone'], $payload['user_name'], $chat->client_rfc);
                    return;
                }

                $this->whatsAppService->sendDocumentCategoryOptions($payload['user_phone'], $payload['user_name'], $rfcFromIntent, $categories);
                $chat->update(['action' => self::ACTION_REQUEST_DOCUMENT_CATEGORY]);

            } else {
                Log::warning("RFC mismatch in ask document categories. Chat RFC: {$chat->client_rfc}, Intent RFC: {$rfcFromIntent}");
                $this->whatsAppService->sendTextMessage($payload['user_phone'], "Hubo un problema de seguridad (RFC). Por favor, reinicia la conversación.");
                $this->resetChatToActionBase($chat, $payload);
            }
        } elseif ($userMessage === self::INTENT_IA_CHAT) {
            $this->whatsAppService->sendTextMessage($payload['user_phone'], "Has seleccionado chatear con nuestra IA. Escribe tu pregunta:");
            $chat->update(['action' => self::ACTION_IA_CONVERSATION]);
        } elseif ($userMessage === self::INTENT_TALK_TO_AGENT) { // Ya manejado globalmente, pero por si acaso
             $this->handleTalkToAgent($chat, $payload);
        } else {
            $this->whatsAppService->sendTextMessage($payload['user_phone'], "Opción no reconocida. Por favor, selecciona una de la lista.");
            $this->whatsAppService->sendClientOptions($payload['user_phone'], $payload['user_name'], $chat->client_rfc);
        }
    }

    // Manejo de categorías de documentos
    private function handleRequestDocumentCategoryState(Chat $chat, array $payload): void
    {
        $userMessage = $payload['user_message']; // e.g., cho_doc_cat_constancias_XAXX010101000

        if (Str::startsWith($userMessage, self::INTENT_CHOOSE_DOC_CATEGORY_PREFIX)) {
            $partsStr = Str::after($userMessage, self::INTENT_CHOOSE_DOC_CATEGORY_PREFIX);

            // Dividir la categoría del RFC. El RFC puede tener guiones bajos.
            // Asumimos que la categoría no tiene guiones bajos, o si los tiene, es parte del nombre.
            // El RFC es la última parte después del último guion bajo si la categoría puede tenerlos.
            // Si la categoría es simple (sin '_'), el primer explode es suficiente.
            // Ejemplo más robusto: encontrar el último '_'
            $lastUnderscorePos = strrpos($partsStr, '_');
            if ($lastUnderscorePos === false) {
                Log::warning("Invalid format for choose document category ID (no underscore for RFC): {$userMessage}");
                $this->whatsAppService->sendTextMessage($payload['user_phone'], "Opción de categoría no válida. Intenta de nuevo.");
                $this->resendCategoryOptionsOrClientMenu($chat, $payload);
                return;
            }

            $category = substr($partsStr, 0, $lastUnderscorePos);
            $rfcFromIntent = substr($partsStr, $lastUnderscorePos + 1);


            if ($chat->client_rfc === $rfcFromIntent) {
                $filesSent = $this->sendClientDocuments($payload['user_phone'], $payload['user_name'], $rfcFromIntent, $category);
                if ($filesSent) {
                    // $this->whatsAppService->sendTextMessage($payload['user_phone'], "Se han procesado los documentos de la categoría '{$category}'. ¿Necesitas algo más?");
                    // No enviar mensaje aquí, sendClientDocuments ya informa.
                    $chat->update(['action' => self::ACTION_DOWNLOAD_DOCS_CONFIRMED]);
                    // Directamente preguntar si necesita algo más o mostrar opciones de nuevo
                    $this->handleDownloadConfirmedState($chat, $payload);


                } else {
                    $this->whatsAppService->sendTextMessage($payload['user_phone'], "No se pudieron enviar documentos de la categoría '{$category}'. Puedes intentar con otra categoría o ver otras opciones.");
                    // Volver a las opciones del cliente en lugar de re-enviar categorías
                    $chat->update(['action' => self::ACTION_VALIDATE_CLIENT_OPTIONS]);
                    $this->whatsAppService->sendClientOptions($payload['user_phone'], $payload['user_name'], $chat->client_rfc);
                }
            } else {
                Log::warning("RFC mismatch in choose document category. Chat RFC: {$chat->client_rfc}, Intent RFC: {$rfcFromIntent}");
                $this->whatsAppService->sendTextMessage($payload['user_phone'], "Hubo un problema de seguridad (RFC-CAT). Por favor, reinicia la conversación.");
                $this->resetChatToActionBase($chat, $payload);
            }
        } else {
            $this->whatsAppService->sendTextMessage($payload['user_phone'], "Por favor, selecciona una categoría de la lista.");
            $this->resendCategoryOptionsOrClientMenu($chat, $payload);
        }
    }

    private function resendCategoryOptionsOrClientMenu(Chat $chat, array $payload): void
    {
        $categories = ClientFile::where('client_rfc', $chat->client_rfc)
                                  ->whereNotNull('category')->where('category', '!=', '')
                                  ->distinct()->orderBy('category')->pluck('category')->all();
        if(!empty($categories)) {
            $this->whatsAppService->sendDocumentCategoryOptions($payload['user_phone'], $payload['user_name'], $chat->client_rfc, $categories);
            $chat->update(['action' => self::ACTION_REQUEST_DOCUMENT_CATEGORY]); // Mantener estado para nueva selección
        } else {
            // Si no hay categorías (quizás un error transitorio), volver a opciones de cliente
            $this->whatsAppService->sendTextMessage($payload['user_phone'], "No se encontraron categorías. Volviendo a opciones de cliente.");
            $this->whatsAppService->sendClientOptions($payload['user_phone'], $payload['user_name'], $chat->client_rfc);
            $chat->update(['action' => self::ACTION_VALIDATE_CLIENT_OPTIONS]);
        }
    }

    private function handleIaConversationState(Chat $chat, array $payload): void
    {
        // Si el usuario escribe "salir", "terminar", "menú" o algo similar, volver a opciones.
        // $exitCommands = ['salir', 'terminar', 'menu', 'menú', 'cancelar'];
        // if (in_array(Str::lower($payload['user_message']), $exitCommands)) {
        //     $chat->update(['action' => $chat->is_client ? self::ACTION_VALIDATE_CLIENT_OPTIONS : self::ACTION_BASE]);
        //     if ($chat->is_client) {
        //         $this->whatsAppService->sendClientOptions($payload['user_phone'], $payload['user_name'], $chat->client_rfc);
        //     } else {
        //         $this->whatsAppService->sendInitialButtons($payload['user_phone'], $payload['user_name']);
        //     }
        //     return;
        // }

        // Continuar conversación con IA
        $botResponse = $this->geminiService->chatWithIA($payload['user_message'], $payload['user_name'], (array)$chat->context);
        $this->whatsAppService->sendTextMessage($payload['user_phone'], $botResponse); //. "\n\n_(Escribe 'salir' para volver al menú)_"
        // El estado sigue siendo ACTION_IA_CONVERSATION
    }


    private function handleDownloadConfirmedState(Chat $chat, array $payload): void
    {
        // Después de enviar documentos, preguntar si necesita algo más o volver a opciones.
        // Este estado es para manejar la respuesta inmediata después de la descarga.
        // Por ejemplo, si el usuario dice "gracias" o "sí".
        // Si la intención es agradecimiento, podemos responder amablemente.
        if (in_array($chat->user_intention, ['despedida_agradecimiento'])) {
            $this->whatsAppService->sendTextMessage($payload['user_phone'], "De nada, ¡estamos para servirte! ¿Puedo ayudarte en algo más?");
        } else {
            $this->whatsAppService->sendTextMessage($payload['user_phone'], "¿Necesitas algo más o deseas ver otras opciones?");
        }
        // Volver a las opciones de cliente
        $chat->update(['action' => self::ACTION_VALIDATE_CLIENT_OPTIONS]);
        //$this->whatsAppService->sendClientOptions($payload['user_phone'], $payload['user_name'], $chat->client_rfc);
    }

    // --- LÓGICA DE FUNCIONALIDADES ---

    private function handleTalkToAgent(Chat $chat, array $payload): void
    {
        // Primero, verificar si el usuario actual es un cliente identificado
        if (!$chat->is_client || !$chat->client_id) {
            // Si no es un cliente identificado pero quiere hablar con un agente (ej. un prospecto)
            // Podrías tener una lógica para prospectos aquí, o simplemente el mensaje genérico.
            Log::info("User {$payload['user_phone']} (not fully identified as client) requested an agent.");
            $this->whatsAppService->sendTextMessage($payload['user_phone'], "Estamos buscando un agente disponible para ti. En breve se comunicarán contigo.");
            // Aquí podrías notificar a un grupo general de agentes o crear una tarea en un CRM.
            // Por ejemplo, enviar un email o una notificación a un canal de Slack.
            // Mail::to(config('mail.support_address'))->send(new ProspectAgentRequestMail($payload));
            $chat->update(['action' => self::ACTION_AWAITING_AGENT]);
            return;
        }

        $client = Client::with('agent')->find($chat->client_id);

        if ($client && $client->agent) {
            $agent = $client->agent;
            $agentMessage = "Hola *{$agent->agent_name}*.\n\nEl cliente *{$client->client_name} {$client->client_lname}* solicita tu atención.\n\nContáctalo cuando tengas oportunidad: {$client->client_phone}\n\nHora: " . Carbon::now()->isoFormat('LLL');

            if ($agent->agent_phone) { // Asumiendo que el agente tiene un WhatsApp
                try {
                    $this->whatsAppService->sendTextMessage($agent->agent_phone, $agentMessage);
                    $this->whatsAppService->sendTextMessage($payload['user_phone'], "Hemos notificado a tu agente asignado: \n\n*{$agent->agent_name} {$agent->agent_lname}* se pondrá en contacto contigo pronto.");
                    $chat->update(['action' => self::ACTION_AWAITING_AGENT]);
                } catch (Exception $e) {
                    Log::error("Failed to notify agent {$agent->agent_name} via WhatsApp: " . $e->getMessage());
                    $this->whatsAppService->sendTextMessage($payload['user_phone'], "Hubo un problema al notificar a tu agente. No te preocupes, un representante se comunicará contigo.");
                    // Aquí podrías tener un fallback, como enviar un email al agente o a un supervisor.
                }
            } else {
                Log::warning("Agente {$agent->agent_name} (ID: {$agent->id}) no tiene número de teléfono configurado para notificaciones.");
                $this->whatsAppService->sendTextMessage($payload['user_phone'], "Tu agente asignado no tiene un canal de notificación directa configurado, pero hemos registrado tu solicitud. Un representante se comunicará contigo.");
                // Notificación interna alternativa (email, Slack, etc.)
            }
        } else {
            $this->whatsAppService->sendTextMessage($payload['user_phone'], "En breve un agente se comunicará contigo para atender tu solicitud. Estamos asignando tu caso.");
            Log::info("Client {$client->client_name} (ID: {$client->id}) requested agent, but no agent is assigned or client/agent model issue.");
            // Aquí también podrías tener una lógica para asignar un agente o notificar a un pool.
        }
        $chat->update(['action' => self::ACTION_AWAITING_AGENT]);
    }


    //mejora

    private function sendClientDocuments(string $userPhone, string $userName, string $rfc, string $category): bool
    {
        $files = ClientFile::where('client_rfc', $rfc)
                             ->where('category', $category)
                             ->orderBy('original_file_name') // Opcional: ordenar archivos
                             ->get();

        $filesSentCount = 0;

        if ($files->isEmpty()) {
            $this->whatsAppService->sendTextMessage($userPhone, "No he encontrado archivos en la categoría '{$category}' para el RFC {$rfc}.");
            return false;
        }

        $this->whatsAppService->sendTextMessage($userPhone, "Buscando archivos en la categoría '{$category}' para {$rfc}.\nUn momento...");


        foreach ($files as $fileRecord) {
            // file_path es el path relativo al disco 'public',
            // ej: "clientes/XAXX010101000/constancias/archivo1.pdf"
            if (empty($fileRecord->file_path)) {
                Log::warning("Registro de archivo sin file_path para RFC {$rfc}, Categoría {$category}, Nombre {$fileRecord->file_name}");
                continue;
            }

            try {
                // Verificar si el archivo existe físicamente antes de generar la URL
                if (!Storage::disk('public')->exists($fileRecord->file_path)) {
                    Log::error("Archivo no encontrado en storage para DB record: {$fileRecord->file_path} (RFC: {$rfc}, Cat: {$category})");
                    $this->whatsAppService->sendTextMessage($userPhone, "Error: El archivo '{$fileRecord->file_name}' está registrado pero no se encontró físicamente. Notificaremos a soporte.");
                    continue;
                }

                // $fileUrl = Storage::disk('public')->url($fileRecord->file_path);

                $fileUrl = config('app.url_temp')."storage/".$fileRecord->file_path ;

                // Forzar HTTPS si es necesario y APP_URL está configurado para HTTPS
                if (!Str::startsWith($fileUrl, 'https://') && Str::startsWith(config('app.url_temp'), 'https')) {
                    $fileUrl = Str::replaceFirst('http://', 'https://', $fileUrl);
                }

                if (!Str::startsWith($fileUrl, 'https://')) {
                     Log::error("No se pudo generar una URL HTTPS para el archivo: {$fileRecord->file_path}. APP_URL: " . config('app.url'));
                     $this->whatsAppService->sendTextMessage($userPhone, "Error al generar el enlace seguro para '{$fileRecord->file_name}'. Notificaremos a soporte.");
                     continue;
                }
                //Log::info("el url ees: {$fileUrl}");
                $this->whatsAppService->sendDocument(
                    $userPhone,
                    $fileUrl,
                    "Archivo de {$category}: {$fileRecord->original_file_name}", // Caption más específico
                    $fileRecord->original_file_name
                );

                $filesSentCount++;
                if ($filesSentCount >= 5) {
                    $this->whatsAppService->sendTextMessage($userPhone, "Se han enviado {$filesSentCount} archivos de '{$category}'. Si esperabas más de esta categoría, contacta a soporte o revisa si están en otra categoría.");
                    break;
                }
            } catch(Exception $e) {
                 Log::error("Error al procesar o enviar el archivo {$fileRecord->file_name} (Path: {$fileRecord->file_path}): ".$e->getMessage());
                 $this->whatsAppService->sendTextMessage($userPhone, "Hubo un problema al intentar enviar el archivo '{$fileRecord->file_name}'.");
            }
        }

        if ($filesSentCount === 0 && !$files->isEmpty()) {
            // Se encontraron registros en BD pero ninguno se pudo enviar (ej. todos fallaron la validación HTTPS o no existen)
            $this->whatsAppService->sendTextMessage($userPhone, "Se encontraron registros para la categoría '{$category}', pero no se pudo enviar ningún archivo. Por favor, contacta a soporte.");
            return false;

        } elseif ($filesSentCount > 0 && $filesSentCount < $files->count()) {

             $this->whatsAppService->sendTextMessage($userPhone, "Se enviaron {$filesSentCount} de {$files->count()} archivos encontrados en '{$category}'. Algunos pudieron tener problemas.");

        } elseif ($filesSentCount > 0) {

             $this->whatsAppService->sendTextMessage($userPhone, "Se han enviado todos los archivos encontrados ({$filesSentCount}) para la categoría '{$category}'.");
        }
        // Si filesSentCount es 0 y $files->isEmpty() ya fue manejado al inicio.

        return $filesSentCount > 0;
    }

    // private function sendClientDocuments(string $userPhone, string $userName, string $rfc): bool
    // {
    //     $basePath = 'app/public/clientes/' . $rfc; // Relativo a storage_path()
    //     $fullBasePath = storage_path($basePath);
    //     $filesSentCount = 0;

    //     if (!is_dir($fullBasePath)) {
    //         Log::warning("Directorio de documentos no encontrado para RFC: {$rfc} en {$fullBasePath}");
    //         $this->whatsAppService->sendTextMessage($userPhone, "No se encontró un directorio de documentos para el RFC: {$rfc}.");
    //         return false;
    //     }

    //     // Asegúrate que `php artisan storage:link` se haya ejecutado.
    //     // Los archivos deben estar en `storage/app/public/RFC/...`
    //     // Y serán accesibles vía `public/storage/RFC/...`
    //     $documentBaseUrl = config('services.whatsapp.document_base_url');

    //     foreach (scandir($fullBasePath) as $file) {
    //         if ($file !== '.' && $file !== '..') {
    //             $fileUrl = Storage::disk('public')->url($rfc . '/' . $file); // Opción más robusta si el disco 'public' está bien configurado
    //             //$fileUrl = $documentBaseUrl . '/' . $rfc . '/' . $file;

    //             // Validar que la URL es accesible públicamente antes de intentar enviarla
    //             // Esta validación puede ser compleja; por ahora asumimos que la URL es correcta.

    //             $this->whatsAppService->sendDocument(
    //                 $userPhone,
    //                 $fileUrl,
    //                 "Archivo para {$userName} (RFC: {$rfc})",
    //                 $file
    //             );
    //             $filesSentCount++;
    //             if ($filesSentCount >= 5) { // Limitar cantidad de archivos por tanda para no saturar
    //                 $this->whatsAppService->sendTextMessage($userPhone, "Se han enviado {$filesSentCount} archivos. Si esperas más, por favor espera un momento y revisa.");
    //                 break;
    //             }
    //         }
    //     }

    //     if ($filesSentCount === 0) {
    //         $this->whatsAppService->sendTextMessage($userPhone, "No he encontrado archivos para el RFC {$rfc} en este momento. Intenta más tarde o contacta a soporte.");
    //         return false;
    //     }
    //     return true;
    // }

    /**
     * Método para ser llamado por un Job programado para enviar recordatorios.
     * Ejemplo: php artisan schedule:run
     */
    public function sendScheduledReminders()
    {
        // Obtener chats inactivos por más de X tiempo (ej. 10 minutos)
        // Y que no estén en un estado de espera (como ACTION_AWAITING_AGENT)
        $inactiveChats = Chat::where('updated_at', '<', Carbon::now()->subMinutes(config('app.chat_inactivity_minutes', 10)))
            ->whereNotIn('action', [self::ACTION_AWAITING_AGENT, self::ACTION_IA_CONVERSATION]) // No molestar si está esperando agente o en chat IA
            ->get();

        foreach ($inactiveChats as $chat) {
            $reminderMessage = "Hola {$chat->user_name}, ¿sigues ahí? Si necesitas algo más, estoy a tu disposición. Puedes escribir 'menú' para ver las opciones.";
            try {
                $this->whatsAppService->sendTextMessage($chat->user_phone, $reminderMessage);
                // Actualizar el timestamp para no enviar recordatorios repetidamente muy rápido,
                // o cambiar a un estado 'reminded'.
                $chat->touch();
                Log::info("Reminder sent to: {$chat->user_phone}");
            } catch (Exception $e) {
                Log::error("Failed to send reminder to {$chat->user_phone}: " . $e->getMessage());
            }
        }
        return "Reminders processed: " . $inactiveChats->count(); // Para logs del scheduler
    }
}
