<?php

namespace Tests\Feature\WhatsApp;

use App\Services\GeminiService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gemini.api_key' => 'test-key',
            'services.gemini.url' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent',
            'services.gemini.thinking_level' => 'low',
        ]);
    }

    public function test_agent_intent_is_parsed_from_a_response_with_thought_parts(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [[
            'content' => ['parts' => [
                ['text' => 'Analizando la petición del agente...', 'thought' => true],
                ['text' => '{"intent": "fetch_document", "client_name": "Juan", "document_name": "constancias"}'],
            ]],
            'finishReason' => 'STOP',
        ]]])]);

        $intent = app(GeminiService::class)->processMessage('dame la constancia de Juan');

        $this->assertSame(['intent' => 'fetch_document', 'client_name' => 'Juan', 'document_name' => 'constancias'], $intent);

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('x-goog-api-key', 'test-key')
                && !str_contains($request->url(), 'key=')
                && $request['generationConfig']['thinkingConfig'] === ['thinkingLevel' => 'low']
                && $request['generationConfig']['maxOutputTokens'] >= 1024;
        });
    }

    public function test_chat_reply_ignores_thought_parts(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [[
            'content' => ['parts' => [
                ['text' => 'pensando', 'thought' => true],
                ['text' => 'El IVA general es del 16%.'],
            ]],
        ]]])]);

        $this->assertSame('El IVA general es del 16%.', app(GeminiService::class)->chatWithIA('¿IVA?', 'Ana'));
    }

    public function test_api_errors_fall_back_gracefully(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'model not found']], 404)]);

        $this->assertSame('', app(GeminiService::class)->processMessage('hola'));
    }
}
