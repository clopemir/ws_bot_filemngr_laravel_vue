<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class GeminiService
{
    private string $apiKey;
    private string $apiUrl;
    private array $defaultIntentPromptContext;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->apiUrl = config('services.gemini.url');

        if (!$this->apiKey || !$this->apiUrl) {
            Log::critical('Gemini service credentials (api_key or url) are not configured.');
            // throw new \InvalidArgumentException('Gemini service credentials are not configured.');
        }

        // Contexto base para la detección de intenciones
        $this->defaultIntentPromptContext = [
            'Eres un clasificador de intenciones para un chatbot de una consultoría fiscal. Debes identificar la intención principal del usuario.',
            'Las intenciones posibles son: [saludo|despedida_agradecimiento|solicitud_documentos|consulta_rfc|hablar_agente|pregunta_general_fiscal|pregunta_no_relacionada].',
            'Si el usuario quiere descargar archivos o pide constancias/opiniones, la intención es [solicitud_documentos].',
            'Si el usuario pregunta por su RFC o quiere validarlo, la intención es [consulta_rfc].',
            'Si el usuario quiere hablar con una persona, asesor o agente, la intención es [hablar_agente].',
            'Si el usuario hace una pregunta sobre impuestos, SAT, leyes fiscales, etc., la intención es [pregunta_general_fiscal].',
            'Si la intención no es clara o es una pregunta muy general no fiscal, responde [pregunta_no_relacionada].',
            'Responde SOLO con la etiqueta de la intención, por ejemplo: [saludo] o [hablar_agente]. No agregues texto adicional.'
        ];
    }

    private function makeRequest(array $contents, ?array $generationConfig = null)
    {
        if (!$this->apiKey || !$this->apiUrl) {
            Log::error('Cannot make Gemini request, service not configured properly.');
            return null;
        }

        $payload = ['contents' => $contents, 'generationConfig' => $this->withThinking($generationConfig ?? [])];

        try {
            $response = $this->client()
                ->post($this->apiUrl, $payload)
                ->throw()
                ->json();

            $text = $this->extractText($response);
            if ($text === '') {
                Log::warning('Gemini response missing expected text part.', ['finish_reason' => $response['candidates'][0]['finishReason'] ?? 'N/A']);
                return null;
            }
            return $text;

        } catch (Exception $e) {
            Log::error("Error in Gemini API request: {$e->getMessage()}", [
                'payload' => $this->maskGeminiPayload($payload),
                'status_code' => method_exists($e, 'getCode') ? $e->getCode() : 'N/A',
                'response_body' => method_exists($e, 'getResponse') && $e->getResponse() ? $e->getResponse()->body() : 'N/A',
            ]);
            return null;
        }
    }

    public function processMessage(string $message)
    {
        $prompt = $this->buildPrompt($message);

        Log::info('Processing message with Gemini.', ['message' => Str::limit($prompt, 100)]);

        try {

            $response = $this->client()
                ->post($this->apiUrl, [
                    'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                    'generationConfig' => $this->withThinking([
                        'temperature' => 0.3,
                        'maxOutputTokens' => 1024,
                        'responseMimeType' => 'application/json',
                    ]),
                ])
                ->throw();

            $responseText = $this->extractText($response->json() ?? []);

            if ($responseText !== '') {
                $jsonResponse = trim(str_replace(['```json', '```'], '', $responseText));

                Log::info('Gemini response received.', ['response' => Str::limit($jsonResponse, 200)]);

                return json_decode($jsonResponse, true) ?: '';

            } else {
                Log::warning('Gemini response was empty or invalid.', ['finish_reason' => $response->json('candidates.0.finishReason', 'N/A')]);
                return '';
            }
        } catch (Exception $e) {
            Log::error('Error processing message with Gemini.', ['error' => Str::limit($e->getMessage(), 300)]);
            return '';
        }
    }

    /**
     * Los modelos Gemini 3.x razonan antes de responder y esos tokens cuentan contra maxOutputTokens.
     * Para clasificar y conversar basta con razonamiento bajo (más rápido, más barato y sin respuestas vacías).
     */
    private function withThinking(array $generationConfig): array
    {
        if ($level = config('services.gemini.thinking_level')) {
            $generationConfig['thinkingConfig'] = ['thinkingLevel' => $level];
        }

        return $generationConfig;
    }

    /**
     * Une las partes de texto de la respuesta, ignorando las partes de razonamiento.
     */
    private function extractText(array $response): string
    {
        $parts = $response['candidates'][0]['content']['parts'] ?? [];

        return Str::trim(collect($parts)
            ->reject(fn ($part) => !empty($part['thought']))
            ->pluck('text')
            ->filter()
            ->implode(''));
    }

    /**
     * Cliente HTTP con la API key en cabecera (no en la URL, para que no aparezca en logs ni excepciones).
     */
    private function client(): PendingRequest
    {
        return Http::retry(2, 100)
            ->timeout(20)
            ->withHeaders(['x-goog-api-key' => $this->apiKey]);
    }

    private function maskGeminiPayload(array $payload): array
    {
        // Si el payload contiene información sensible del usuario, enmascararla aquí.
        // Por ejemplo, el texto del usuario.
        if (isset($payload['contents'])) {
            foreach ($payload['contents'] as &$content) {
                if (isset($content['parts'][0]['text'])) {
                    // Podrías acortar o reemplazar el texto del usuario para el log
                    // $content['parts'][0]['text'] = Str::limit($content['parts'][0]['text'], 50) . ' [MASKED]';
                }
            }
        }
        return $payload;
    }


    public function detectIntent(string $userMessage, array $historyMessages = []): string
    {
        $systemInstruction = implode("\n", $this->defaultIntentPromptContext);

        $contents = [
            ['role' => 'user', 'parts' => [['text' => $systemInstruction]]],
            ['role' => 'model', 'parts' => [['text' => 'Entendido. Estoy listo para clasificar la intención.']]]
        ];

        // Añadir historial si existe para dar contexto
        foreach ($historyMessages as $msg) {
            // Asumir que $historyMessages es una lista de strings de mensajes previos del usuario
             $contents[] = ['role' => 'user', 'parts' => [['text' => $msg]]];
             $contents[] = ['role' => 'model', 'parts' => [['text' => '(Mensaje previo procesado)']]]; // Placeholder para el modelo
        }

        $contents[] = ['role' => 'user', 'parts' => [['text' => "Clasifica la siguiente frase del usuario: \"{$userMessage}\""]]];

        $intent = $this->makeRequest($contents, ['temperature' => 0.2, 'maxOutputTokens' => 50]); // Baja temperatura para clasificación

        // Limpiar la respuesta para que sea solo la etiqueta
        if ($intent) {
            preg_match('/\[(.*?)\]/', $intent, $matches);
            $intent = $matches[1] ?? 'pregunta_no_relacionada'; // Fallback si no hay corchetes
        } else {
            $intent = 'pregunta_no_relacionada'; // Fallback si la API falla
        }

        Log::info("Gemini detected intent: [{$intent}] for message: \"{$userMessage}\"");
        return $intent;
    }

    public function chatWithIA(string $userMessage, string $userName, array $chatHistory = []): string
    {
        //$systemPrompt = "Eres PAI, un asistente virtual amigable y experto en temas fiscales de México. Tu objetivo es ayudar a {$userName} con sus consultas. Mantén un tono casual y servicial. Si no sabes una respuesta, indícalo honestamente y sugiere consultar a un especialista de FP Corporativo. Para mejorar la interacción trata a {$userName} como si fuera tu compañero de trabajo. Puedes presentarte la primera vez y explicar un alcance de tus capacidades. No es necesario que incluyas el nombre del usuario en tus respuestas, pero si lo haces, usa siempre el nombre {$userName}.";
        $systemPrompt = "Eres PAI, un asistente virtual amigable y experto en temas fiscales de México. Tu objetivo es ayudar a {$userName} con sus consultas.
        Reglas de Comportamiento:
        1.  **Tono Casual y Servicial:** Mantén un tono de compañero de trabajo. Trata a {$userName} como un colega.
        2.  **Sé Conciso:** Responde directamente a la pregunta del usuario. No repitas información de mensajes anteriores a menos que se te pida explícitamente.
        3.  **Manejo de Interacciones Sociales:** Si el usuario te da las gracias o usa frases cortas como 'ok', responde de forma breve y amigable (ej. '¡De nada! ¿Necesitas algo más?') y espera su siguiente consulta. No vuelvas a generar la respuesta anterior.
        4.  **Honestidad:** Si no sabes una respuesta, indícalo honestamente y sugiere consultar a un especialista de FP Corporativo.
        5.  **Presentación:** Puedes presentarte la primera vez y explicar tu alcance.
        6.  **Uso del Nombre:** No es necesario que incluyas el nombre del usuario, pero si lo haces, usa siempre {$userName}.";

        $contents = [
            ['role' => 'user', 'parts' => [['text' => $systemPrompt]]]
        ];

        if (empty($chatHistory)) {
            $contents[] = ['role' => 'model', 'parts' => [['text' => "¡Hola {$userName}! Soy PAI, tu asistente fiscal. ¿En qué te puedo ayudar hoy?"]]];
        }

        // $contents = [
        //     ['role' => 'user', 'parts' => [['text' => $systemPrompt]]],
        //     // La respuesta inicial del modelo para guiar la conversación.
        //     ['role' => 'model', 'parts' => [['text' => "¡Hola {$userName}! Soy PAI, tu asistente fiscal. ¿En qué te puedo ayudar hoy?"]]]
        // ];

        $historyLimit = 10; // Limitar el historial a los últimos 10 mensajes para evitar sobrecargar la IA
        $limitHistory = array_slice($chatHistory, -$historyLimit); // Asegurarse de que no exceda el límite


        // Añadir historial de chat (simplificado)
        // $chatHistory debe ser un array de arrays ['role' => 'user'/'model', 'parts' => [['text' => ...]]]
        foreach ($limitHistory as $entry) {
            if (isset($entry['role']) && isset($entry['content'])) { // Adaptar si la estructura del historial es diferente
                 $contents[] = ['role' => $entry['role'], 'parts' => [['text' => $entry['content']]]];
            }
        }

        //eliminar saltos de linea en el mensaje del usuario
        //$userMessage = str_replace(["\n", "\r"], ' ', $userMessage);
        $contents[] = ['role' => 'user', 'parts' => [['text' => $userMessage]]];

        $botResponse = $this->makeRequest($contents, ['temperature' => 0.7, 'maxOutputTokens' => 2048]);

        if (!$botResponse) {
            return "Lo siento, {$userName}, tuve un problema para procesar tu consulta en este momento. Por favor, intenta de nuevo más tarde.";
        }

        Log::info("Gemini IA response generated for user {$userName}");
        return $botResponse;
    }

    public function buildPrompt(string $userMessage) {
        return <<<PROMPT
        Eres un API que procesa texto de un agente fiscal y extrae información.
        Tu objetivo es identificar la intención del agente y extraer el nombre de un cliente y el nombre de un documento.
        Responde SIEMPRE y ÚNICAMENTE con un objeto JSON.

        IMPORTANTE: El usuario puede escribir con errores ortográficos o de tipeo o puede no escribir el nombre del cliente entre comillas. Debes interpretar su intención lo mejor posible.

        El JSON debe tener tres claves: 'intent', 'client_name', y 'document_name'.
        - 'intent': Puede ser 'fetch_document' o 'chat'.
        - 'client_name': El nombre del cliente que se menciona. Si no se menciona, debe ser null.
        - 'document_name': El nombre o tipo de documento solicitado. Si no se menciona, debe ser null.

        Si el usuario está pidiendo un archivo, constancia, documento, etc., de un cliente, la intención es 'fetch_document'.
        Si el usuario está haciendo una pregunta general, saludando o conversando, la intención es 'chat'.

        Ejemplos:
        1. Texto: "Hola, quiero la constancia del cliente 'Cliente Prueba 1'"
           Respuesta: {"intent": "fetch_document", "client_name": "Cliente Prueba 1", "document_name": "constancias"}

        2. Texto: "dame la Opinión de cumplimiento de la empresa 'Servicios Contables MX'"
           Respuesta: {"intent": "fetch_document", "client_name": "Servicios Contables MX", "document_name": "opiniones"}

        3. Texto: "Buenas tardes, ¿cuál es el IVA para alimentos de mascotas?"
           Respuesta: {"intent": "ia_conversation", "client_name": null, "document_name": null}

        4. Texto: "necesito los impuestos de 'Ferretería El Martillo Feliz'"
            Respuesta: {"intent": "fetch_document", "client_name": "Ferretería El Martillo Feliz", "document_name": "impuestos"}

        5. Texto: "Hola, cómo estás?"
            Respuesta: {"intent": "ia_conversation", "client_name": null, "document_name": null}

        Ahora, procesa el siguiente texto:
        Texto: "{$userMessage}"
        Respuesta:
        PROMPT;
    }
}
