<?php

namespace Tests\Feature\WhatsApp;

use App\Models\Chat;
use App\Models\VerificationCode;
use App\Notifications\ClientAccessCodeNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

class ClientVerificationFlowTest extends WhatsAppTestCase
{
    private const CLIENT_TO = '525512345678';
    private const STRANGER_TO = '525599999999';
    private const AGENT_TO = '525511112222';

    public function test_webhook_without_valid_signature_is_rejected(): void
    {
        $this->postWebhook(['type' => 'text', 'text' => ['body' => 'hola']], signature: 'sha256=forged')
            ->assertForbidden();

        $this->assertDatabaseCount('chats', 0);
        Http::assertNothingSent();
    }

    public function test_webhook_is_rejected_when_app_secret_is_missing_even_with_app_env_local(): void
    {
        config(['services.whatsapp.app_secret' => null]);
        $this->app['env'] = 'local'; // Así está configurado hoy el servidor de producción

        $this->text('hola')->assertStatus(503);
        $this->assertDatabaseCount('chats', 0);
    }

    public function test_unsigned_webhooks_can_be_allowed_explicitly_for_development(): void
    {
        config(['services.whatsapp.app_secret' => null, 'services.whatsapp.allow_unsigned_webhooks' => true]);

        $this->text('hola')->assertOk();
        $this->assertDatabaseCount('chats', 1);
    }

    public function test_status_notifications_are_acknowledged(): void
    {
        $body = json_encode(['entry' => [['changes' => [['value' => ['statuses' => [['status' => 'read']]]]]]]]);

        $this->call('POST', '/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $body, self::APP_SECRET),
        ], $body)->assertOk();
    }

    public function test_registered_phone_with_rfc_is_verified_by_phone(): void
    {
        $this->chatFor(self::CLIENT_WA, Chat::ACTION_REQUEST_RFC);

        $this->text('PEJJ800101AB1')->assertOk();

        $chat = Chat::firstWhere('wa_id', self::CLIENT_WA);
        $this->assertSame($this->client->id, $chat->client_id);
        $this->assertSame(Chat::VERIFIED_BY_PHONE, $chat->verification_method);
        $this->assertTrue($chat->hasValidSession());
        $this->assertSame(Chat::ACTION_CLIENT_OPTIONS, $chat->action);
        $this->assertSame('list', collect($this->sentTo(self::CLIENT_TO))->last()['interactive']['type']);
    }

    public function test_unregistered_phone_requires_code_sent_to_the_owner(): void
    {
        Notification::fake();
        $this->chatFor(self::STRANGER_WA, Chat::ACTION_REQUEST_RFC);

        $this->text('PEJJ800101AB1', self::STRANGER_WA)->assertOk();

        $chat = Chat::firstWhere('wa_id', self::STRANGER_WA);
        $this->assertNull($chat->client_id, 'No debe haber sesión antes del segundo factor');
        $this->assertSame(Chat::ACTION_REQUEST_OTP, $chat->action);
        $this->assertStringContainsString('código de 6 dígitos', $this->lastTextTo(self::STRANGER_TO));

        // El código va SOLO al correo registrado del titular, nunca al solicitante.
        $code = null;
        Notification::assertSentOnDemand(ClientAccessCodeNotification::class, function ($notification, $channels, $notifiable) use (&$code) {
            $code = $notification->code;
            return $notifiable->routes['mail'] === 'juan@example.com';
        });
        foreach ($this->textsTo(self::STRANGER_TO) as $text) {
            $this->assertStringNotContainsString($code, $text);
        }

        // El agente recibe la alerta con el número solicitante.
        $this->assertStringContainsString('NO registrado', $this->lastTextTo(self::AGENT_TO));

        $this->text($code, self::STRANGER_WA)->assertOk();

        $chat->refresh();
        $this->assertSame($this->client->id, $chat->client_id);
        $this->assertSame(Chat::VERIFIED_BY_OTP, $chat->verification_method);
        $this->assertNotNull(VerificationCode::first()->consumed_at);
    }

    public function test_no_code_is_issued_when_mail_only_writes_to_the_log(): void
    {
        Notification::fake();
        config(['mail.default' => 'log']);
        $this->chatFor(self::STRANGER_WA, Chat::ACTION_REQUEST_RFC);

        $this->text('PEJJ800101AB1', self::STRANGER_WA);

        Notification::assertNothingSent();
        $this->assertSame(0, VerificationCode::count());
        $this->assertNull(Chat::firstWhere('wa_id', self::STRANGER_WA)->client_id);
    }

    public function test_unknown_rfc_from_unregistered_phone_gets_the_same_answer(): void
    {
        Notification::fake();
        $this->chatFor(self::STRANGER_WA, Chat::ACTION_REQUEST_RFC);
        $this->chatFor('5215588888888', Chat::ACTION_REQUEST_RFC);

        $this->text('PEJJ800101AB1', self::STRANGER_WA);
        $this->text('XAXX010101000', '5215588888888');

        // Misma respuesta exista o no el RFC: el bot no sirve para averiguar quién es cliente.
        $this->assertSame($this->lastTextTo(self::STRANGER_TO), $this->lastTextTo('525588888888'));
        $this->assertSame(1, VerificationCode::count());
    }

    public function test_code_is_locked_after_max_attempts(): void
    {
        Notification::fake();
        $this->chatFor(self::STRANGER_WA, Chat::ACTION_REQUEST_RFC);
        $this->text('PEJJ800101AB1', self::STRANGER_WA);

        $this->text('000000', self::STRANGER_WA);
        $this->text('111111', self::STRANGER_WA);
        $this->assertStringContainsString('Te quedan *1*', $this->lastTextTo(self::STRANGER_TO));
        $this->text('222222', self::STRANGER_WA);

        $chat = Chat::firstWhere('wa_id', self::STRANGER_WA);
        $this->assertNull($chat->client_id);
        $this->assertSame(Chat::ACTION_BASE, $chat->action);
        $this->assertStringContainsString('Se agotaron los intentos', $this->lastTextTo(self::STRANGER_TO));
        $this->assertStringContainsString('agotó los intentos', $this->lastTextTo(self::AGENT_TO));
    }

    public function test_expired_code_is_rejected(): void
    {
        Notification::fake();
        $this->chatFor(self::STRANGER_WA, Chat::ACTION_REQUEST_RFC);
        $this->text('PEJJ800101AB1', self::STRANGER_WA);

        $code = null;
        Notification::assertSentOnDemand(ClientAccessCodeNotification::class, function ($n) use (&$code) {
            $code = $n->code;
            return true;
        });

        $this->travel(11)->minutes();
        $this->text($code, self::STRANGER_WA);

        $this->assertNull(Chat::firstWhere('wa_id', self::STRANGER_WA)->client_id);
        $this->assertStringContainsString('ya venció', $this->lastTextTo(self::STRANGER_TO));
    }

    public function test_codes_per_client_are_limited_per_day(): void
    {
        Notification::fake();

        foreach (range(1, 4) as $i) {
            $from = '52155000000' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $this->chatFor($from, Chat::ACTION_REQUEST_RFC);
            $this->text('PEJJ800101AB1', $from);
        }

        $this->assertSame(3, VerificationCode::count());
        Notification::assertSentOnDemandTimes(ClientAccessCodeNotification::class, 3);
    }

    public function test_rfc_attempts_are_rate_limited_per_phone(): void
    {
        $this->chatFor(self::CLIENT_WA, Chat::ACTION_REQUEST_RFC);

        foreach (range(1, 5) as $i) {
            $this->text('XAXX010101000');
        }
        $this->text('PEJJ800101AB1'); // Incluso el RFC correcto queda bloqueado

        $this->assertStringContainsString('límite de intentos', $this->lastTextTo(self::CLIENT_TO));
        $this->assertNull(Chat::firstWhere('wa_id', self::CLIENT_WA)->client_id);
    }

    public function test_inactive_client_cannot_be_verified(): void
    {
        $this->client->update(['client_status' => false]);
        $this->chatFor(self::CLIENT_WA, Chat::ACTION_REQUEST_RFC);

        $this->text('PEJJ800101AB1');

        $this->assertNull(Chat::firstWhere('wa_id', self::CLIENT_WA)->client_id);
    }

    public function test_old_phone_stops_working_after_the_client_phone_changes(): void
    {
        $this->client->update(['client_phone' => '5577777777']);
        $this->chatFor(self::CLIENT_WA, Chat::ACTION_REQUEST_RFC);

        $this->text('PEJJ800101AB1');

        $chat = Chat::firstWhere('wa_id', self::CLIENT_WA);
        $this->assertNull($chat->client_id);
        $this->assertSame(Chat::ACTION_REQUEST_OTP, $chat->action);
    }

    public function test_session_expires(): void
    {
        $this->verifiedChat();
        $this->travel(16)->minutes();

        $this->tap(Chat::INTENT_ASK_DOC_CATEGORIES);

        $chat = Chat::firstWhere('wa_id', self::CLIENT_WA);
        $this->assertNull($chat->client_id);
        $this->assertSame(Chat::ACTION_REQUEST_RFC, $chat->action);
        $this->assertStringContainsString('sesión expiró', $this->lastTextTo(self::CLIENT_TO));
    }

    public function test_menu_command_closes_the_session(): void
    {
        $this->verifiedChat();

        $this->text('menú');

        $this->assertFalse(Chat::firstWhere('wa_id', self::CLIENT_WA)->hasValidSession());
    }

    public function test_duplicate_webhooks_are_processed_once(): void
    {
        $this->chatFor(self::CLIENT_WA, Chat::ACTION_REQUEST_RFC);
        $message = ['id' => 'wamid.same', 'type' => 'text', 'text' => ['body' => 'XAXX010101000']];

        $this->postWebhook($message);
        $this->postWebhook($message);

        $this->assertCount(1, $this->textsTo(self::CLIENT_TO));
    }

    public function test_verification_data_is_not_stored_in_chat_context(): void
    {
        $this->chatFor(self::CLIENT_WA, Chat::ACTION_REQUEST_RFC);

        $this->text('PEJJ800101AB1');

        $this->assertStringNotContainsString('PEJJ800101AB1', json_encode(Chat::firstWhere('wa_id', self::CLIENT_WA)->context));
    }
}
