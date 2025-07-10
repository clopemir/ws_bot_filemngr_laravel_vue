<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{

    public const ACTION_BASE = 'base';
    public const ACTION_REQUEST_RFC = 'request_rfc';
    public const ACTION_CLIENT_OPTIONS = 'validate_client_options'; // Se mantiene nombre original por consistencia
    public const ACTION_REQUEST_DOCUMENT_CATEGORY = 'request_document_category';
    public const ACTION_AWAITING_AGENT = 'awaiting_agent';
    public const ACTION_IA_CONVERSATION = 'ia_conversation';

    const INTENT_CLIENT = 'client';
    const INTENT_NO_CLIENT = 'no_client';
    const INTENT_TALK_TO_AGENT = 'agent_chat'; // ID del botón/lista para hablar con agente
    const INTENT_DOWNLOAD_DOCS_PREFIX = 'dld_'; // Prefijo para IDs de descarga de documentos
    const INTENT_IA_CHAT = 'ia_chat';


    //Nuevos estados para categorias

    const INTENT_ASK_DOC_CATEGORIES_PREFIX = 'ask_doc_cat_'; // Payload: ask_doc_cat_{RFC}
    const INTENT_CHOOSE_DOC_CATEGORY_PREFIX = 'cho_doc_cat_'; // Payload: cho_doc_cat_{CATEGORY}_{RFC}

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
    ];

    protected $casts = [
        'context' => 'array'
    ];

    public function addMessageToContext(string $message, ?string $role = 'user'): void
    {
        // Ejemplo de estructura tipo contexto para IA o historial de chat
        $context = $this->context ?? []; // Suponiendo que tienes una columna `context` en la tabla `chats`

        $context[] = [
            'role' => $role,
            'content' => $message,
            'timestamp' => now()->toIso8601String()
        ];

        // Guardar el contexto actualizado
        $this->context = $context;
        $this->save();
    }
    public function getLastMessage(): ?string
    {
        // Retorna el último mensaje del contexto, si existe
        return end($this->context)['content'] ?? null;
    }
  
}
