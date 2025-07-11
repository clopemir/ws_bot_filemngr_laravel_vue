<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\WhatsApp\WaController;
use App\Exceptions\WhatsAppApiException;

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

    private function maskSensitiveData(array $data): array
    {
        // Implementa lógica para enmascarar datos sensibles si es necesario
        // Por ejemplo, si envías tokens o información personal en el cuerpo.
        return $data;
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

        $headerImageUrl = config('app.url') . '/images/bot.png';

        return $this->sendMessage($to, [
            "type" => "interactive",
            "interactive" => [
                "type" => "button",
                "header" => [
                    "type" => "image",
                    'image' => ['link' => config('whatsapp_bot.welcome_image_url')]
                    //"image" => ["link" => $headerImageUrl] // URL pública de la imagen
                ],
                'body' => ['text' => trans('whatsapp.welcome', ['name' => $userName])],
                'footer' => ['text' => trans('whatsapp.welcome_footer')],
                "action" => [
                    "buttons" => [
                        ['type' => 'reply', 'reply' => ['id' => 'client', 'title' => trans('whatsapp.buttons.is_client')]],
                        ['type' => 'reply', 'reply' => ['id' => 'no_client', 'title' => trans('whatsapp.buttons.is_not_client')]],
                    ]
                ]
            ]
        ]);

    }

    /**
     * Envía la lista de opciones para un cliente verificado.
     */
    public function sendClientOptions(string $to, string $userName, string $rfc)
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
                                ['id' => "ask_doc_cat_".$rfc, 'title' => 'Descargar Archivos', 'description' => 'Constancias, Opiniones, etc.'],
                                ['id' => 'agent_chat', 'title' => 'Hablar con mi Agente', 'description' => 'Atención personalizada'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function sendNonClientOptions(string $to, string $userName)
    {
         $data = [
            "messaging_product" => "whatsapp",
            "recipient_type"=> "individual",
            "to" => $to,
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
                                //["id" => "be_client", "title" => "Quiero ser Cliente", "description" => "Pasos y beneficios"]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        return $this->makeRequest("{$this->baseUrl}/messages", $data);
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
     * Nuevo para categorias
     * Envía una lista de categorías de documentos para que el usuario seleccione.
     */
    public function sendDocumentCategoryOptions(string $to, string $userName, string $rfc, array $categories)
    {
        if (empty($categories)) {
            $this->sendTextMessage($to, "No hay categorías de documentos disponibles para {$rfc} en este momento.");
            return;
        }

        $rows = [];
        foreach ($categories as $category) {
            if (empty($category)) continue;
            $rows[] = [
                // El ID es construido por el controlador y luego parseado por él mismo.
                "id" => "cho_doc_cat_{$category}_{$rfc}",
                "title" => Str::title(str_replace(['_', '-'], ' ', $category)), // Formatear nombre de categoría
                "description" => "Descargar archivos de " . Str::lower($category)
            ];
        }

        if (empty($rows)) {
            $this->sendTextMessage($to, "No se encontraron categorías válidas de documentos para mostrar para el RFC {$rfc}.");
            return;
        }
        // Limitar número de filas por sección si es necesario (WhatsApp tiene límites)
        if (count($rows) > 5) {
            Log::warning("Demasiadas categorías ({count($rows)}) para RFC {$rfc}. Se mostrarán las primeras 5.");
            $rows = array_slice($rows, 0, 5);
             // Considerar enviar un mensaje adicional si hay más de 10 categorías
        }

        return $this->sendMessage($to, [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'header' => ['type' => 'text', 'text' => trans('whatsapp.doc_categories.header')],
                'body' => ['text' => trans('whatsapp.doc_categories.body', ['name' => $userName, 'rfc' => $rfc])],
                'footer' => ['text' => trans('whatsapp.doc_categories.footer')],
                'action' => [
                    'button' => trans('whatsapp.buttons.view_categories'),
                    'sections' => [['title' => 'Categorías', 'rows' => $rows]],
                ],
            ],
        ]);

    }

    public function sendDocument(string $to, string $documentUrl, string $caption, string $filename)
    {
        // Validar que la URL sea HTTPS, WhatsApp lo requiere para documentos.
        if (!Str::startsWith($documentUrl, 'https://')) {
            Log::error("URL de documento no es HTTPS: {$documentUrl}. No se puede enviar.");
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
                //'response_body' => $response->body() ?? 'N/A',
            ]);
            throw new WhatsAppApiException(
                message: "Error al comunicarse con la API de WhatsApp: " . $e->getMessage(),
                code: $e->getCode(),
                previous: $e
            ); // O re-lanzar una excepción personalizada
        }
    }

}
