<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class GeminiProvider implements AiProvider
{
    public function name(): string
    {
        return 'gemini';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.gemini.api_key')) && filled(config('services.gemini.url'));
    }

    public function complete(string $system, array $messages, array $options = []): string
    {
        $generationConfig = [
            'temperature' => $options['temperature'] ?? 0.7,
            'maxOutputTokens' => $options['max_tokens'] ?? 1024,
        ];

        if ($options['json'] ?? false) {
            $generationConfig['responseMimeType'] = 'application/json';
        }

        // Los modelos 3.x razonan antes de responder y esos tokens cuentan contra maxOutputTokens.
        if ($level = config('services.gemini.thinking_level')) {
            $generationConfig['thinkingConfig'] = ['thinkingLevel' => $level];
        }

        try {
            // La API key va en cabecera para que no aparezca en logs ni excepciones.
            // Solo se reintentan fallos de red: reintentar un 429 gastaría más cupo.
            $response = Http::retry(2, 100, fn ($e) => $e instanceof ConnectionException)
                ->timeout(15)
                ->withHeaders(['x-goog-api-key' => config('services.gemini.api_key')])
                ->post(config('services.gemini.url'), [
                    'systemInstruction' => ['parts' => [['text' => $system]]],
                    'contents' => array_map(fn (array $message) => [
                        'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                        'parts' => [['text' => $message['content']]],
                    ], $messages),
                    'generationConfig' => $generationConfig,
                ])
                ->throw();
        } catch (RequestException $e) {
            throw new AiProviderException(
                'Gemini respondió con error: ' . Str::limit($e->response->body(), 200),
                $e->response->status(),
                is_numeric($e->response->header('Retry-After')) ? (int) $e->response->header('Retry-After') : null,
            );
        } catch (Throwable $e) {
            throw new AiProviderException('No se pudo contactar a Gemini: ' . Str::limit($e->getMessage(), 200));
        }

        // Une las partes de texto e ignora las de razonamiento.
        $text = trim(collect($response->json('candidates.0.content.parts', []))
            ->reject(fn ($part) => !empty($part['thought']))
            ->pluck('text')
            ->filter()
            ->implode(''));

        if ($text === '') {
            throw new AiProviderException('Gemini devolvió una respuesta vacía: ' . $response->json('candidates.0.finishReason', 'N/A'));
        }

        return $text;
    }
}
