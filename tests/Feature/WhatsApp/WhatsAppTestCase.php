<?php

namespace Tests\Feature\WhatsApp;

use App\Models\Agent;
use App\Models\Chat;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

abstract class WhatsAppTestCase extends TestCase
{
    use RefreshDatabase;

    protected const APP_SECRET = 'test-app-secret';
    protected const CLIENT_PHONE = '5512345678';           // Teléfono registrado (10 dígitos)
    protected const CLIENT_WA = '5215512345678';           // Cómo llega desde WhatsApp
    protected const STRANGER_WA = '5215599999999';         // Número no registrado
    protected const AGENT_PHONE = '5511112222';

    protected Agent $agent;
    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://bot.test',
            'services.whatsapp.token' => 'test-token',
            'services.whatsapp.phone_number_id' => '123',
            'services.whatsapp.app_secret' => self::APP_SECRET,
            'whatsapp_bot.document_send_delay_ms' => 0,
            'whatsapp_bot.security.otp_whatsapp_template' => null,
        ]);
        \Illuminate\Support\Facades\URL::forceRootUrl('https://bot.test');
        \Illuminate\Support\Facades\URL::forceScheme('https');

        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out']]])]);

        $this->agent = Agent::create([
            'agent_name' => 'Ana', 'agent_lname' => 'Agente', 'agent_phone' => self::AGENT_PHONE,
            'agent_mail' => 'ana@example.com', 'agent_status' => true,
        ]);

        $this->client = Client::create([
            'agent_id' => $this->agent->id, 'client_name' => 'Juan', 'client_lname' => 'Pérez',
            'client_rfc' => 'pejj800101ab1', 'client_phone' => self::CLIENT_PHONE,
            'client_mail' => 'juan@example.com', 'client_status' => true,
        ]);
    }

    protected function postWebhook(array $message, string $from = self::CLIENT_WA, ?string $signature = null): TestResponse
    {
        $body = json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [['changes' => [['value' => [
                'contacts' => [['wa_id' => $from, 'profile' => ['name' => 'Usuario']]],
                'messages' => [array_merge(['from' => $from, 'id' => 'wamid.' . Str::random(16)], $message)],
            ]]]]],
        ]);

        return $this->call('POST', '/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature ?? 'sha256=' . hash_hmac('sha256', $body, self::APP_SECRET),
        ], $body);
    }

    protected function text(string $body, string $from = self::CLIENT_WA): TestResponse
    {
        return $this->postWebhook(['type' => 'text', 'text' => ['body' => $body]], $from);
    }

    protected function tap(string $id, string $from = self::CLIENT_WA): TestResponse
    {
        return $this->postWebhook(['type' => 'interactive', 'interactive' => [
            'type' => 'list_reply', 'list_reply' => ['id' => $id, 'title' => 'x'],
        ]], $from);
    }

    /**
     * Crea el chat y lo deja en el estado indicado (como si ya hubiera recibido la bienvenida).
     */
    protected function chatFor(string $from, string $action = Chat::ACTION_BASE, array $attributes = []): Chat
    {
        return Chat::create(array_merge([
            'wa_id' => $from, 'user_name' => 'Usuario', 'user_phone' => '52' . substr($from, -10),
            'context' => [], 'user_intention' => '', 'action' => $action, 'is_client' => false,
        ], $attributes));
    }

    protected function verifiedChat(string $from = self::CLIENT_WA, string $method = Chat::VERIFIED_BY_PHONE): Chat
    {
        return $this->chatFor($from, Chat::ACTION_CLIENT_OPTIONS, [
            'client_id' => $this->client->id, 'client_rfc' => $this->client->client_rfc,
            'is_client' => true, 'verified_at' => now(), 'verification_method' => $method,
        ]);
    }

    /**
     * Mensajes salientes a la API de WhatsApp dirigidos a un número.
     *
     * @return array<int, array>
     */
    protected function sentTo(string $to): array
    {
        return Http::recorded()
            ->map(fn ($pair) => $pair[0])
            ->filter(fn (Request $r) => ($r->data()['to'] ?? null) === $to)
            ->map(fn (Request $r) => $r->data())
            ->values()->all();
    }

    protected function textsTo(string $to): array
    {
        return array_values(array_map(
            fn ($m) => $m['text']['body'],
            array_filter($this->sentTo($to), fn ($m) => ($m['type'] ?? null) === 'text')
        ));
    }

    protected function lastTextTo(string $to): ?string
    {
        $texts = $this->textsTo($to);

        return end($texts) ?: null;
    }
}
