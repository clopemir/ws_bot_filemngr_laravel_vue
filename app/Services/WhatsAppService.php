<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\WhatsApp\WaController;

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
            // throw new \InvalidArgumentException('WhatsApp service credentials are not configured.');
        }
    }

    private function makeRequest(string $endpoint, array $data, string $method = 'POST')
    {
        if (!$this->token || !$this->phoneNumberId) {
             Log::error('Cannot send WhatsApp message, service not configured properly.');
             return null;
        }
        try {
            $response = Http::withToken($this->token)
                ->{$method}($endpoint, $data)
                ->throw(); // Lanza excepción para errores 4xx/5xx

            Log::info('WhatsApp API request successful.', ['endpoint' => $endpoint, 'response_status' => $response->status()]);
            return $response->json();

        } catch (Exception $e) {
            Log::error("Error in WhatsApp API request to {$endpoint}: {$e->getMessage()}", [
                'data' => $this->maskSensitiveData($data), // No loguear tokens o PII directamente
                'status_code' => method_exists($e, 'getCode') ? $e->getCode() : 'N/A',
                'response_body' => method_exists($e, 'getResponse') && $e->getResponse() ? $e->getResponse()->body() : 'N/A',
            ]);
            return null; // O re-lanzar una excepción personalizada
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
        $data = [
            "messaging_product" => "whatsapp",
            "status" => "read",
            "message_id" => $messageId
        ];
        // El endpoint para marcar como leído es el mismo que para enviar mensajes
        return $this->makeRequest("{$this->baseUrl}/messages", $data);
    }

    public function sendTextMessage(string $to, string $text)
    {
        $data = [
            "messaging_product" => "whatsapp",
            "recipient_type" => "individual",
            "to" => $to,
            "type" => "text",
            "text" => [
                "preview_url" => false, // Generalmente false para bots, a menos que envíes links intencionalmente
                "body" => $text
            ]
        ];
        return $this->makeRequest("{$this->baseUrl}/messages", $data);
    }

    public function sendInitialButtons(string $to, string $userName)
    {
        // Considerar una imagen de cabecera genérica o configurable
       // $headerImageUrl = config('app.url') . '/images/bot_saludo.png'; // Ejemplo, asegúrate que esta imagen exista en public/images
        $headerImageUrl = config('app.url') . '/images/bot.png';


        $data = [
            "messaging_product" => "whatsapp",
            "recipient_type" => "individual",
            "to" => $to,
            "type" => "interactive",
            "interactive" => [
                "type" => "button",
                "header" => [
                    "type" => "image",
                    "image" => ["link" => $headerImageUrl] // URL pública de la imagen
                ],
                "body" => ["text" => "Hola *{$userName}* ¡un gusto saludarte! 👋🏼\n\nSoy *PAI*, el Asistente Virtual de *FP Corporativo*, diseñado para ayudar 😉\n\nPor favor, selecciona una de las opciones para comenzar:"],
                "footer" => ["text" => "Tu asistente virtual."], // Pie de página más corto
                "action" => [
                    "buttons" => [
                        ["type" => "reply", "reply" => ["id" => "client", "title" => "Soy Cliente"]],
                        ["type" => "reply", "reply" => ["id" => "no_client", "title" => "Aún no soy Cliente"]]
                    ]
                ]
            ]
        ];
        return $this->makeRequest("{$this->baseUrl}/messages", $data);
    }

    public function sendClientOptions(string $to, string $userName, string $rfc)
    {
        $data = [
            "messaging_product" => "whatsapp",
            "recipient_type" => "individual",
            "to" => $to,
            "type" => "interactive",
            "interactive" => [
                "type" => "list",
                "header" => ["type" => "text", "text" => "Opciones para Clientes"],
                "body" => ["text" => "Hola *{$userName}* 👋🏼\n\nEstas son tus opciones disponibles como cliente de FP Corporativo:\n"],
                "footer" => ["text" => "Selecciona una opción"],
                "action" => [
                    "button" => 'Ver Opciones',
                    "sections" => [
                        [
                            "title" => 'Servicios Principales',
                            "rows" => [
                                ["id" =>  WaController::INTENT_ASK_DOC_CATEGORIES_PREFIX . $rfc, "title" => "Descargar Archivos", "description" => "Constancias, Opiniones, Acuses"],
                                ["id" => WaController::INTENT_TALK_TO_AGENT, "title" => "Hablar con mi Agente", "description" => "Atención personalizada"],
                                //["id" => "ia_chat", "title" => "Chatear con IA (PAI)", "description" => "Consultas generales y fiscales"]
                            ]
                        ]
                        // Puedes agregar más secciones o filas si es necesario
                    ]
                ]
            ]
        ];
        return $this->makeRequest("{$this->baseUrl}/messages", $data);
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
        $headerImageUrl = config('app.url_temp') . '/images/logo-azul.png';


        $data = [
            "messaging_product" => "whatsapp",
            "recipient_type" => "individual",
            "to" => $to,
            "type" => "interactive",
            "interactive" => [
                "type" => "cta_url",
                "header" => [
                    "type" => "image",
                    "image" => ["link" => "https://fpcorporativo.com/uploads/20978518593.png"] // URL pública de la imagen
                ],
                "body" => ["text" => "Hola *{$userName}* \n\n*Necesitas ayuda?*\n\n*Conoce nuestros servicios, agenda tu cita y contáctanos fácilmente.*"],
                "action" => [
                    "name" => "cta_url",
                    "parameters" => [
                        "display_text" => "¡Haz clic aquí!",
                        "url" => "https://fpcorporativo.com"
                    ]
                ],
                "footer" => ["text" => "Estamos seguros de que podemos ayudarte."], // Pie de página más corto
            ]
        ];
        return $this->makeRequest("{$this->baseUrl}/messages", $data);
    }




    //Nuevo para categorias

     /**
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
                "id" => WaController::INTENT_CHOOSE_DOC_CATEGORY_PREFIX . trim($category) . '_' . $rfc,
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


        $data = [
            "messaging_product" => "whatsapp",
            "recipient_type" => "individual",
            "to" => $to,
            "type" => "interactive",
            "interactive" => [
                "type" => "list",
                "header" => ["type" => "text", "text" => "Selecciona Categoría"],
                "body" => ["text" => "Hola *{$userName}* 👋🏼\n\nElige la categoría de documentos que deseas descargar para el RFC: *{$rfc}*\n"],
                "footer" => ["text" => "Tus archivos disponibles"],
                "action" => [
                    "button" => "Ver Categorías",
                    "sections" => [
                        [
                            "title" => "Categorías",
                            "rows" => $rows
                        ]
                    ]
                ]
            ]
        ];
        return $this->makeRequest("{$this->baseUrl}/messages", $data);
    }


    public function sendDocument(string $to, string $documentUrl, string $caption, string $filename)
    {
        // Validar que la URL sea HTTPS, WhatsApp lo requiere para documentos.
        if (!Str::startsWith($documentUrl, 'https://')) {
            Log::error("URL de documento no es HTTPS: {$documentUrl}. No se puede enviar.");
            // Podrías intentar enviar un mensaje de error al usuario aquí.
            $this->sendTextMessage($to, "Hubo un error al preparar tu documento (URL no segura). Por favor, contacta a soporte.");
            return null;
        }

        $data = [
            "messaging_product" => "whatsapp",
            "recipient_type" => "individual",
            "to" => $to,
            "type" => "document",
            "document" => [
                "link" => $documentUrl,
                "caption" => $caption,
                "filename" => $filename
            ]
        ];
        return $this->makeRequest("{$this->baseUrl}/messages", $data);
    }
}
