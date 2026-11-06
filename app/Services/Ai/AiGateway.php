<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Punto único para llamar a la IA: respeta los topes de cada proveedor y pasa al siguiente si uno falla.
 */
class AiGateway
{
    private const MAX_COOLDOWN_SECONDS = 3600;

    /** @var array<string, AiProvider> */
    private array $available;

    public function __construct(GeminiProvider $gemini, OpenRouterProvider $openRouter)
    {
        $this->available = ['gemini' => $gemini, 'openrouter' => $openRouter];
    }

    /**
     * @return string|null Null si ningún proveedor pudo responder.
     */
    public function complete(string $system, array $messages, array $options = []): ?string
    {
        foreach ($this->providers() as $provider) {
            if (!$this->canCall($provider)) {
                continue;
            }

            $this->registerCall($provider);

            try {
                return $provider->complete($system, $messages, $options);
            } catch (AiProviderException $e) {
                Log::warning('Proveedor de IA no disponible; se intenta el siguiente.', [
                    'provider' => $provider->name(),
                    'status' => $e->status,
                    'error' => $e->getMessage(),
                ]);

                if ($e->shouldPauseProvider()) {
                    $seconds = min($e->retryAfterSeconds ?? (int) config('ai.cooldown_seconds', 60), self::MAX_COOLDOWN_SECONDS);
                    Cache::put($this->cooldownKey($provider), true, now()->addSeconds(max(1, $seconds)));
                }
            }
        }

        Log::warning('Ningún proveedor de IA pudo responder.');

        return null;
    }

    /**
     * Consumo del día por proveedor, para el dashboard.
     *
     * @return array<int, array{name: string, configured: bool, used: int, limit: int, paused: bool}>
     */
    public function usage(): array
    {
        return array_map(fn (AiProvider $provider) => [
            'name' => $provider->name(),
            'configured' => $provider->isConfigured(),
            'used' => RateLimiter::attempts($this->dayKey($provider)),
            'limit' => $this->limit($provider, 'per_day'),
            'paused' => Cache::has($this->cooldownKey($provider)),
        ], $this->providers());
    }

    /**
     * @return array<int, AiProvider>
     */
    private function providers(): array
    {
        return array_values(array_filter(array_map(
            fn (string $name) => $this->available[$name] ?? null,
            (array) config('ai.providers', []),
        )));
    }

    private function canCall(AiProvider $provider): bool
    {
        return $provider->isConfigured()
            && !Cache::has($this->cooldownKey($provider))
            && RateLimiter::attempts($this->minuteKey($provider)) < $this->limit($provider, 'per_minute')
            && RateLimiter::attempts($this->dayKey($provider)) < $this->limit($provider, 'per_day');
    }

    private function registerCall(AiProvider $provider): void
    {
        RateLimiter::hit($this->minuteKey($provider), 60);
        RateLimiter::hit($this->dayKey($provider), 90000); // La clave incluye la fecha: basta con que dure más de un día.
    }

    private function limit(AiProvider $provider, string $period): int
    {
        return (int) config("ai.limits.{$provider->name()}.{$period}", 0);
    }

    private function minuteKey(AiProvider $provider): string
    {
        return "ai-calls:{$provider->name()}:minute";
    }

    private function dayKey(AiProvider $provider): string
    {
        // El cupo gratuito de OpenRouter se reinicia a las 00:00 UTC.
        return "ai-calls:{$provider->name()}:" . now('UTC')->format('Y-m-d');
    }

    private function cooldownKey(AiProvider $provider): string
    {
        return "ai-cooldown:{$provider->name()}";
    }
}
