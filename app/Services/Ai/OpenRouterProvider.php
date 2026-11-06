<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class OpenRouterProvider implements AiProvider
{
    public function name(): string
    {
        return 'openrouter';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.openrouter.api_key')) && filled(config('services.openrouter.url'));
    }

    public function complete(string $system, array $messages, array $options = []): string
    {
        $payload = [
            'model' => config('services.openrouter.model'),
            'messages' => array_merge([['role' => 'system', 'content' => $system]], $messages),
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 1024,
        ];

        if (config('services.openrouter.data_collection') === 'deny') {
            $payload['provider'] = ['data_collection' => 'deny'];
        }

        try {
            // Sin reintentos: cada intento cuenta contra el cupo gratuito diario.
            $response = Http::timeout(15)
                ->withToken((string) config('services.openrouter.api_key'))
                ->withHeaders([
                    'HTTP-Referer' => (string) config('app.url'),
                    'X-OpenRouter-Title' => (string) config('app.name'),
                ])
                ->post(config('services.openrouter.url'), $payload)
                ->throw();
        } catch (RequestException $e) {
            throw new AiProviderException(
                'OpenRouter respondió con error: ' . Str::limit($e->response->body(), 200),
                $e->response->status(),
                $this->retryAfter($e->response),
            );
        } catch (Throwable $e) {
            throw new AiProviderException('No se pudo contactar a OpenRouter: ' . Str::limit($e->getMessage(), 200));
        }

        // OpenRouter puede responder 200 con el error del proveedor en el cuerpo.
        if ($error = $response->json('error')) {
            throw new AiProviderException('OpenRouter devolvió un error: ' . Str::limit((string) ($error['message'] ?? ''), 200), (int) ($error['code'] ?? 0) ?: null);
        }

        $text = trim((string) $response->json('choices.0.message.content'));

        if ($text === '') {
            throw new AiProviderException('OpenRouter devolvió una respuesta vacía.');
        }

        return $text;
    }

    private function retryAfter(Response $response): ?int
    {
        if (is_numeric($response->header('Retry-After'))) {
            return (int) $response->header('Retry-After');
        }

        // X-RateLimit-Reset viene en milisegundos desde la época Unix.
        $reset = $response->header('X-RateLimit-Reset');

        return is_numeric($reset) ? max(1, (int) ceil(((int) $reset) / 1000) - time()) : null;
    }
}
