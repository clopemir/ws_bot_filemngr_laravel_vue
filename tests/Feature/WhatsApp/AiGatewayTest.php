<?php

namespace Tests\Feature\WhatsApp;

use App\Services\Ai\AgentAssistant;
use App\Services\Ai\AiGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiGatewayTest extends TestCase
{
    private const GEMINI = 'generativelanguage.googleapis.com/*';
    private const OPENROUTER = 'openrouter.ai/*';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.providers' => ['gemini', 'openrouter'],
            'ai.limits.gemini' => ['per_minute' => 10, 'per_day' => 200],
            'ai.limits.openrouter' => ['per_minute' => 15, 'per_day' => 45],
            'services.gemini.api_key' => 'gemini-key',
            'services.gemini.url' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent',
            'services.gemini.thinking_level' => 'low',
            'services.openrouter.api_key' => 'router-key',
            'services.openrouter.url' => 'https://openrouter.ai/api/v1/chat/completions',
            'services.openrouter.model' => 'openrouter/free',
            'services.openrouter.data_collection' => 'deny',
        ]);

        Cache::flush();
    }

    private function geminiText(string $text): array
    {
        return ['candidates' => [['content' => ['parts' => [
            ['text' => 'pensando', 'thought' => true],
            ['text' => $text],
        ]]]]];
    }

    private function routerText(string $text): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => $text]]]];
    }

    private function ask(): ?string
    {
        return app(AiGateway::class)->complete('sistema', [['role' => 'user', 'content' => 'hola']]);
    }

    private function callsTo(string $host): int
    {
        return Http::recorded()->filter(fn ($pair) => str_contains($pair[0]->url(), $host))->count();
    }

    public function test_gemini_is_used_first_and_thought_parts_are_ignored(): void
    {
        Http::fake([self::GEMINI => Http::response($this->geminiText('respuesta')), self::OPENROUTER => Http::response($this->routerText('otra'))]);

        $this->assertSame('respuesta', $this->ask());
        $this->assertSame(0, $this->callsTo('openrouter.ai'));

        Http::assertSent(fn (Request $request) => $request->hasHeader('x-goog-api-key', 'gemini-key')
            && !str_contains($request->url(), 'key=')
            && $request['generationConfig']['thinkingConfig'] === ['thinkingLevel' => 'low']);
    }

    public function test_falls_back_to_openrouter_when_gemini_fails(): void
    {
        Http::fake([self::GEMINI => Http::response(['error' => ['message' => 'caído']], 500), self::OPENROUTER => Http::response($this->routerText('desde openrouter'))]);

        $this->assertSame('desde openrouter', $this->ask());

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'openrouter.ai')
            && $request->hasHeader('Authorization', 'Bearer router-key')
            && $request['model'] === 'openrouter/free'
            && $request['provider'] === ['data_collection' => 'deny']
            && $request['messages'][0] === ['role' => 'system', 'content' => 'sistema']);
    }

    public function test_daily_limit_stops_calls_to_openrouter(): void
    {
        config(['ai.providers' => ['openrouter'], 'ai.limits.openrouter' => ['per_minute' => 100, 'per_day' => 2]]);
        Http::fake([self::OPENROUTER => Http::response($this->routerText('ok'))]);

        $this->assertSame('ok', $this->ask());
        $this->assertSame('ok', $this->ask());
        $this->assertNull($this->ask());

        $this->assertSame(2, $this->callsTo('openrouter.ai'));
        $this->assertSame(2, app(AiGateway::class)->usage()[0]['used']);
    }

    public function test_per_minute_limit_stops_calls(): void
    {
        config(['ai.providers' => ['openrouter'], 'ai.limits.openrouter' => ['per_minute' => 1, 'per_day' => 45]]);
        Http::fake([self::OPENROUTER => Http::response($this->routerText('ok'))]);

        $this->ask();
        $this->assertNull($this->ask());

        $this->assertSame(1, $this->callsTo('openrouter.ai'));
    }

    public function test_a_429_pauses_the_provider_instead_of_spending_more_calls(): void
    {
        Http::fake([
            self::GEMINI => Http::response(['error' => ['message' => 'cuota']], 429, ['Retry-After' => '120']),
            self::OPENROUTER => Http::response($this->routerText('fallback')),
        ]);

        $this->assertSame('fallback', $this->ask());
        $this->assertSame('fallback', $this->ask());

        $this->assertSame(1, $this->callsTo('generativelanguage'));
        $this->assertTrue(app(AiGateway::class)->usage()[0]['paused']);
    }

    public function test_openrouter_errors_inside_a_200_response_are_treated_as_failures(): void
    {
        config(['ai.providers' => ['openrouter']]);
        Http::fake([self::OPENROUTER => Http::response(['error' => ['code' => 429, 'message' => 'límite']])]);

        $this->assertNull($this->ask());
        $this->assertTrue(app(AiGateway::class)->usage()[0]['paused']);
    }

    public function test_unconfigured_providers_are_skipped(): void
    {
        config(['services.gemini.api_key' => null]);
        Http::fake([self::OPENROUTER => Http::response($this->routerText('ok'))]);

        $this->assertSame('ok', $this->ask());
        $this->assertSame(0, $this->callsTo('generativelanguage'));
    }

    public function test_interpret_accepts_json_wrapped_in_text(): void
    {
        Http::fake([self::GEMINI => Http::response($this->geminiText("Claro:\n```json\n{\"intent\":\"fetch_document\",\"client_name\":\"Juan\",\"document_name\":\"constancias\"}\n```"))]);

        $this->assertSame(
            ['intent' => 'fetch_document', 'client_name' => 'Juan', 'document_name' => 'constancias'],
            app(AgentAssistant::class)->interpret('dame la constancia de Juan'),
        );
    }

    public function test_interpret_treats_invalid_output_as_chat_and_outage_as_null(): void
    {
        Http::fake([self::GEMINI => Http::response($this->geminiText('no es json'))]);
        $this->assertSame('chat', app(AgentAssistant::class)->interpret('hola')['intent']);

        config(['ai.providers' => []]);
        $this->assertNull(app(AgentAssistant::class)->interpret('hola'));
    }
}
