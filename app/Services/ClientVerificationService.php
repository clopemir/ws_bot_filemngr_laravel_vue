<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\Client;
use App\Models\VerificationCode;
use App\Notifications\ClientAccessCodeNotification;
use App\Support\Mask;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Verificación en dos factores del acceso a documentos:
 *
 *  1. Número registrado: si quien escribe usa el teléfono registrado del cliente,
 *     basta con el RFC (Meta autentica el número y el webhook va firmado).
 *  2. Número NO registrado: se genera un código de un solo uso y se envía SOLO a
 *     los medios registrados del titular (correo / WhatsApp). Quien solicita
 *     necesita que el titular le comparta el código.
 */
class ClientVerificationService
{
    public const CODE_VERIFIED = 'verified';
    public const CODE_INVALID = 'invalid';
    public const CODE_EXPIRED = 'expired';
    public const CODE_LOCKED = 'locked';
    public const CODE_NONE = 'none';

    public function __construct(private WhatsAppService $whatsAppService)
    {
    }

    // --- Límite de intentos de RFC por número ---

    public function isLockedOut(string $phone): bool
    {
        return RateLimiter::tooManyAttempts($this->rfcKey($phone), $this->config('rfc_max_attempts', 5));
    }

    public function lockoutMinutes(string $phone): int
    {
        return (int) max(1, ceil(RateLimiter::availableIn($this->rfcKey($phone)) / 60));
    }

    public function registerRfcAttempt(string $phone): void
    {
        RateLimiter::hit($this->rfcKey($phone), $this->config('rfc_decay_minutes', 60) * 60);

        if ($this->isLockedOut($phone)) {
            $this->audit('Número bloqueado por exceso de intentos de RFC.', ['phone' => Mask::phone($phone)]);
        }
    }

    // --- Sesión ---

    public function grantSession(Chat $chat, Client $client, string $method): void
    {
        $chat->update([
            'client_id' => $client->id,
            'client_rfc' => $client->client_rfc,
            'is_client' => true,
            'verified_at' => now(),
            'verification_method' => $method,
            'action' => Chat::ACTION_CLIENT_OPTIONS,
        ]);

        RateLimiter::clear($this->rfcKey($chat->user_phone));

        $this->audit('Sesión de cliente verificada.', [
            'chat_id' => $chat->id,
            'client_id' => $client->id,
            'method' => $method,
            'phone' => Mask::phone($chat->user_phone),
        ]);
    }

    // --- Segundo factor: código de un solo uso ---

    /**
     * Genera y envía un código al titular. Devuelve null si no se pudo enviar
     * (límite diario alcanzado o el cliente no tiene medios de contacto utilizables).
     */
    public function issueCode(Chat $chat, Client $client): ?VerificationCode
    {
        $sentToday = VerificationCode::where('client_id', $client->id)
            ->where('created_at', '>', now()->subDay())
            ->count();

        if ($sentToday >= $this->config('otp_max_per_client_per_day', 3)) {
            $this->audit('Límite diario de códigos alcanzado.', ['client_id' => $client->id, 'phone' => Mask::phone($chat->user_phone)]);
            return null;
        }

        // Un código nuevo invalida cualquier código pendiente del mismo chat.
        $chat->verificationCodes()->whereNull('consumed_at')->update(['consumed_at' => now()]);

        $length = $this->config('otp_length', 6);
        $code = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
        $ttl = $this->config('otp_ttl_minutes', 10);

        $channels = $this->deliverCode($client, $code, $chat->user_phone, $ttl);

        if (empty($channels)) {
            $this->audit('No hay canales disponibles para enviar el código.', ['client_id' => $client->id]);
            return null;
        }

        $verification = $chat->verificationCodes()->create([
            'client_id' => $client->id,
            'requester_phone' => $chat->user_phone,
            'code_hash' => $this->hashCode($code),
            'channels' => $channels,
            'expires_at' => now()->addMinutes($ttl),
        ]);

        $this->audit('Código de verificación emitido para número no registrado.', [
            'chat_id' => $chat->id,
            'client_id' => $client->id,
            'phone' => Mask::phone($chat->user_phone),
            'channels' => $channels,
        ]);

        return $verification;
    }

    /**
     * Valida el código escrito por el usuario.
     *
     * @return array{status: string, remaining?: int, client?: Client}
     */
    public function verifyCode(Chat $chat, string $input): array
    {
        $verification = $chat->verificationCodes()->whereNull('consumed_at')->latest('id')->first();

        if (!$verification) {
            return ['status' => self::CODE_NONE];
        }

        if ($verification->isExpired()) {
            $verification->update(['consumed_at' => now()]);
            return ['status' => self::CODE_EXPIRED];
        }

        $maxAttempts = $this->config('otp_max_attempts', 3);
        $input = preg_replace('/\D+/', '', $input);

        if ($input !== '' && hash_equals($verification->code_hash, $this->hashCode($input))) {
            $verification->update(['consumed_at' => now()]);
            $client = $verification->client;

            if (!$client || !$client->client_status) {
                return ['status' => self::CODE_NONE];
            }

            $this->grantSession($chat, $client, Chat::VERIFIED_BY_OTP);

            return ['status' => self::CODE_VERIFIED, 'client' => $client];
        }

        $verification->increment('attempts');

        if ($verification->attempts >= $maxAttempts) {
            $verification->update(['consumed_at' => now()]);
            $this->audit('Código bloqueado por exceso de intentos.', [
                'chat_id' => $chat->id,
                'client_id' => $verification->client_id,
                'phone' => Mask::phone($chat->user_phone),
            ]);

            return ['status' => self::CODE_LOCKED, 'client' => $verification->client];
        }

        return ['status' => self::CODE_INVALID, 'remaining' => $maxAttempts - $verification->attempts];
    }

    // --- Internos ---

    private function deliverCode(Client $client, string $code, string $requesterPhone, int $ttl): array
    {
        $channels = [];

        if ($this->emailDeliveryAvailable() && filter_var($client->client_mail, FILTER_VALIDATE_EMAIL)) {
            try {
                Notification::route('mail', $client->client_mail)
                    ->notify(new ClientAccessCodeNotification($client, $code, Mask::phone($requesterPhone), $ttl));
                $channels[] = 'email';
            } catch (Throwable $e) {
                Log::error('No se pudo enviar el código por correo.', ['client_id' => $client->id, 'error' => $e->getMessage()]);
            }
        }

        $template = config('whatsapp_bot.security.otp_whatsapp_template');
        if ($template && $client->client_phone) {
            try {
                $this->whatsAppService->sendAuthenticationCode(
                    PhoneNumber::toWhatsApp($client->client_phone),
                    $template,
                    config('whatsapp_bot.security.otp_whatsapp_template_language', 'es_MX'),
                    $code
                );
                $channels[] = 'whatsapp';
            } catch (Throwable $e) {
                Log::error('No se pudo enviar el código por WhatsApp.', ['client_id' => $client->id, 'error' => $e->getMessage()]);
            }
        }

        return $channels;
    }

    /**
     * Con el mailer 'log' el correo no sale del servidor y el código quedaría escrito en el log:
     * ese canal no cuenta como entrega real.
     */
    private function emailDeliveryAvailable(): bool
    {
        if (!$this->config('otp_via_email', 1)) {
            return false;
        }

        if (config('mail.default') === 'log') {
            Log::warning('MAIL_MAILER=log: no se envían códigos por correo. Configura un SMTP real.');
            return false;
        }

        return true;
    }

    private function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    private function rfcKey(string $phone): string
    {
        return 'wa-rfc-attempts:' . substr(PhoneNumber::digits($phone), -10);
    }

    private function config(string $key, int $default): int
    {
        return (int) config("whatsapp_bot.security.{$key}", $default);
    }

    private function audit(string $message, array $context = []): void
    {
        Log::channel('security')->info($message, $context);
    }
}
