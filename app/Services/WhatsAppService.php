<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;
use App\Exceptions\WhatsAppApiException;
use App\Models\Chat;

class WhatsAppService
{
    private string $token;
    private string $phoneNumberId;
    private string $apiVersion;
    private string $baseUrl;

    public function __construct()
    {
        $this->token = config('services.whatsapp.token');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id');
        $this->apiVersion = config('services.whatsapp.api_version');
        $this->baseUrl = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}";

        if (!$this->token || !$this->phoneNumberId) {
            Log::critical('WhatsApp service credentials (token or phone_number_id) are not configured.');
            // Considerar lanzar una excepción si la app no puede funcionar sin esto.
            throw new \InvalidArgumentException('WhatsApp service credentials are not configured.');
        }
    }

    /**
     * ID estable y opaco para una categoría de documentos (no expone datos del cliente).
     */
    public static function categoryOptionId(string $category): string
    {
        return Chat::INTENT_CHOOSE_DOC_CATEGORY_PREFIX . substr(hash('sha256', $category), 0, 16);
    }

    public function markMessageAsRead(string $messageId)
    {

        $this->makeRequest('messages', [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId,
        ]);
    }

    /**
     * Envía un indicador de "escribiendo..."
     */
    public function sendTypingIndicator(string $wam_id)
    {
        $this->makeRequest('messages', [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $wam_id,
            'typing_indicator' => [
                'type' => 'text'
            ]
        ]);
    }

     /**
     * Envía un mensaje de texto simple.
     */
    public function sendTextMessage(string $to, string $text)
    {
        return $this->sendMessage($to, [
            "type" => "text",
            "text" => [
                "preview_url" => false, // Generalmente false para bots, a menos que envíes links intencionalmente
                "body" => $text
            ]
            ]);
    }

     /**
     * Envía los botones de bienvenida iniciales.
     */
    public function sendInitialButtons(string $to, string $userName)
    {

        return $this->sendMessage($to, [
            "type" => "interactive",
            "interactive" => [
                "type" => "button",
                "header" => [
                    "type" => "image",
                    'image' => ['link' => config('whatsapp_bot.welcome_image_url')]
                ],
                'body' => ['text' => trans('whatsapp.welcome', ['name' => $userName])],
                'footer' => ['text' => trans('whatsapp.welcome_footer')],
                "action" => [
                    "buttons" => [
                        ['type' => 'reply', 'reply' => ['id' => Chat::INTENT_CLIENT, 'title' => trans('whatsapp.buttons.is_client')]],
                        ['type' => 'reply', 'reply' => ['id' => Chat::INTENT_NO_CLIENT, 'title' => trans('whatsapp.buttons.is_not_client')]],
                    ]
                ]
            ]
        ]);

    }

    /**
     * Envía la lista de opciones para un cliente verificado.
     */
    public function sendClientOptions(string $to, string $userName)
    {
        return $this->sendMessage($to, [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'header' => ['type' => 'text', 'text' => trans('whatsapp.client_options.header')],
                'body' => ['text' => trans('whatsapp.client_options.body', ['name' => $userName])],
                'footer' => ['text' => trans('whatsapp.client_options.footer')],
                'action' => [
                    'button' => trans('whatsapp.buttons.view_options'),
                    'sections' => [
                        [
                            'title' => trans('whatsapp.client_options.main_services_title'),
                            'rows' => [
                                ['id' => Chat::INTENT_ASK_DOC_CATEGORIES, 'title' => 'Descargar Archivos', 'description' => 'Constancias, Opiniones, etc.'],
                                ['id' => Chat::INTENT_TALK_TO_AGENT, 'title' => 'Hablar con mi Agente', 'description' => 'Atención personalizada'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function sendNonClientOptions(string $to, string $userName)
    {
        return $this->sendMessage($to, [
            "type" => "interactive",
            "interactive" => [
                "type" => "list",
                "header" => ["type" => "text", "text" => "Opciones Disponibles"],
                "body" => ["text" => "Hola *{$userName}* 👋🏼\n\n interesado en FP Corporativo?, estas son tus opciones:\n"],
                "footer" => ["text" => "Selecciona una opción"],
                "action" => [
                    "button" => 'Ver Opciones',
                    "sections" => [
                        [
                            "title" => 'Servicios',
                            "rows" => [
                                ["id" => "req_info", "title" => "Conócenos", "description" => "Detalles de lo que ofrecemos"],
                            ]
                        ]
                    ]
                ]
            ]
        ]);
    }

    public function sendInfo(string $to, string $userName)
    {
        //$headerImageUrl = config('app.url_temp') . '/images/logo-azul.png';

        return $this->sendMessage($to, [
            "type" => "interactive",
            "interactive" => [
                "type" => "cta_url",
                "header" => [
                    "type" => "image",
                    "image" => ["link" => "https://fpcorporativo.com/uploads/20978518593.png"]
                ],
                "body" => ["text" => "Hola *{$userName}* \n\n*Necesitas ayuda?*\n\n*Conoce nuestros servicios, agenda tu cita y contáctanos fácilmente.*"],
                "action" => [
                    "name" => "cta_url",
                    "parameters" => [
                        "display_text" => "¡Haz clic aquí!",
                        "url" => "https://fpcorporativo.com"
                    ]
                ],
                "footer" => ["text" => "Estamos seguros de que podemos ayudarte."],

            ]
        ]);
    }

    /**
     * Envía la lista de categorías de documentos del cliente verificado.
     * Los IDs de cada opción son hashes opacos; el cliente se toma de la sesión, nunca del mensaje.
     */
    public function sendDocumentCategoryOptions(string $to, string $userName, array $categories)
    {
        $rows = [];
        foreach ($categories as $category) {
            if (blank($category)) continue;
            $rows[] = [
                'id' => self::categoryOptionId($category),
                'title' => Str::limit(Str::title(str_replace(['_', '-'], ' ', $category)), 21), // Máx. 24 caracteres
                'description' => Str::limit('Descargar archivos de ' . Str::lower($category), 69), // Máx. 72 caracteres
            ];
        }

        // WhatsApp permite hasta 10 filas por lista.
        if (count($rows) > 10) {
            Log::warning('Demasiadas categorías para una lista de WhatsApp; se mostrarán las primeras 10.', ['total' => count($rows)]);
            $rows = array_slice($rows, 0, 10);
        }

        return $this->sendMessage($to, [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'header' => ['type' => 'text', 'text' => trans('whatsapp.doc_categories.header')],
                'body' => ['text' => trans('whatsapp.doc_categories.body', ['name' => $userName])],
                'footer' => ['text' => trans('whatsapp.doc_categories.footer')],
                'action' => [
                    'button' => trans('whatsapp.buttons.view_categories'),
                    'sections' => [['title' => 'Categorías', 'rows' => $rows]],
                ],
            ],
        ]);
    }

    /**
     * Envía un código de verificación usando una plantilla de "Autenticación" aprobada en Meta.
     * Las plantillas permiten escribir al titular aunque no haya conversado con el bot en las últimas 24 h.
     */
    public function sendAuthenticationCode(string $to, string $template, string $language, string $code)
    {
        return $this->sendMessage($to, [
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => $language],
                'components' => [
                    ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $code]]],
                    ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => $code]]],
                ],
            ],
        ]);
    }

    public function sendDocument(string $to, string $documentUrl,  string $filename, ?string $caption = '')
    {
        // Validar que la URL sea HTTPS, WhatsApp lo requiere para documentos.
        if (!Str::startsWith($documentUrl, 'https://')) {
            Log::error('URL de documento no es HTTPS. No se puede enviar.');
            throw new WhatsAppApiException(trans('whatsapp.errors.unsafe_url'));
        }

        return $this->sendMessage($to, [
            'type' => 'document',
            'document' => [
                'link' => $documentUrl,
                'caption' => $caption,
                'filename' => $filename,
            ],
        ]);
    }

    /**
     * Método base para enviar cualquier tipo de mensaje.
     */
    private function sendMessage(string $to, array $messageData): array
    {
        $payload = array_merge([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
        ], $messageData);

        return $this->makeRequest('messages', $payload);
    }

    private function makeRequest(string $endpoint, array $data, string $method = 'POST')
    {
        try {
            $response = Http::withToken($this->token)
                ->baseUrl($this->baseUrl)
                ->{$method}($endpoint, $data)
                ->throw(); // Lanza excepción

            Log::info('WhatsApp API request successful.', ['endpoint' => $endpoint, 'response_status' => $response->status()]);
            return $response->json();

        } catch (Exception $e) {
            Log::error("Error en la petición a la API de WhatsApp: {$e->getMessage()}", [
                'endpoint' => $endpoint,
                'exception_code' => $e->getCode(),
                'response_body' => $e instanceof RequestException ? Str::limit($e->response->body(), 500) : 'N/A',
            ]);
            throw new WhatsAppApiException(
                message: "Error al comunicarse con la API de WhatsApp: " . $e->getMessage(),
                code: $e->getCode(),
                previous: $e
            ); // O re-lanzar una excepción personalizada
        }
    }

}
