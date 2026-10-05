<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chat extends Model
{

    public const ACTION_BASE = 'base';
    public const ACTION_REQUEST_RFC = 'request_rfc';
    public const ACTION_REQUEST_OTP = 'request_otp';
    public const ACTION_CLIENT_OPTIONS = 'validate_client_options'; // Se mantiene nombre original por consistencia
    public const ACTION_REQUEST_DOCUMENT_CATEGORY = 'request_document_category';
    public const ACTION_AWAITING_AGENT = 'awaiting_agent';
    public const ACTION_IA_CONVERSATION = 'ia_conversation';

    public const VERIFIED_BY_PHONE = 'phone';
    public const VERIFIED_BY_OTP = 'otp';

    // IDs de botones/listas. Ya NO llevan el RFC: el cliente se toma siempre de la sesión verificada.
    const INTENT_CLIENT = 'client';
    const INTENT_NO_CLIENT = 'no_client';
    const INTENT_TALK_TO_AGENT = 'agent_chat';
    const INTENT_ASK_DOC_CATEGORIES = 'ask_doc_cat';
    const INTENT_CHOOSE_DOC_CATEGORY_PREFIX = 'cho_doc_cat_'; // Payload: cho_doc_cat_{hash de la categoría}

    // Número máximo de mensajes que se guardan en el contexto del chat.
    private const CONTEXT_LIMIT = 30;

    protected $fillable = [
        'wa_id',
        'user_name',
        'user_phone',
        'client_rfc',
        'client_id',
        'context',
        'user_intention',
        'action',
        'is_client',
        'verified_at',
        'verification_method',
    ];

    protected $casts = [
        'context' => 'array',
        'is_client' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function verificationCodes(): HasMany
    {
        return $this->hasMany(VerificationCode::class);
    }

    /**
     * Indica si el chat tiene una sesión de cliente verificada y vigente.
     */
    public function hasValidSession(): bool
    {
        if (!$this->client_id || !$this->verified_at) {
            return false;
        }

        $ttl = (int) config('whatsapp_bot.security.session_ttl_minutes', 15);

        return $this->verified_at->gt(now()->subMinutes($ttl));
    }

    /**
     * Cierra la sesión del cliente y regresa el chat al estado inicial.
     */
    public function clearSession(string $action = self::ACTION_BASE): void
    {
        $this->update([
            'action' => $action,
            'client_rfc' => null,
            'client_id' => null,
            'verified_at' => null,
            'verification_method' => null,
        ]);
    }

    public function addMessageToContext(string $message, ?string $role = 'user'): void
    {
        $context = $this->context ?? [];

        $context[] = [
            'role' => $role,
            'content' => $message,
            'timestamp' => now()->toIso8601String()
        ];

        // Evita que la columna crezca indefinidamente.
        $this->context = array_slice($context, -self::CONTEXT_LIMIT);
        $this->save();
    }

    public function getLastMessage(): ?string
    {
        $context = $this->context ?? [];

        return end($context)['content'] ?? null;
    }
}
