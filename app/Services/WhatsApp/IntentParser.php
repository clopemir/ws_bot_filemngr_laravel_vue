<?php

namespace App\Services\WhatsApp;

use App\Models\Chat;
use Illuminate\Support\Str;

class IntentParser
{
    /**
     * Parsea el mensaje/ID del usuario para determinar su intención.
     *
     * Los IDs de botones y listas SOLO se reconocen cuando el mensaje es de tipo
     * 'interactive'. Así nadie puede "inventar" una opción escribiéndola como texto.
     */
    public static function parse(string $userMessage, string $messageType = 'text'): object
    {
        if ($messageType === 'interactive') {
            return self::parseInteractive($userMessage);
        }

        $userMessageLower = Str::lower(Str::squish($userMessage));

        // Comandos globales
        if (in_array($userMessageLower, ['cancelar', 'menu', 'menú', 'reset', 'salir'])) {
            return self::createIntent('reset');
        }
        if (Str::contains($userMessageLower, ['gracias', 'adiós', 'adios', 'hasta luego', 'bye', 'nada más', 'es todo', 'no necesito nada más', 'no necesito nada'])) {
            return self::createIntent('end_conversation');
        }

        return self::createIntent('text_input', ['text' => $userMessage]);
    }

    private static function parseInteractive(string $id): object
    {
        return match (true) {
            $id === Chat::INTENT_CLIENT => self::createIntent('is_client'),
            $id === Chat::INTENT_NO_CLIENT => self::createIntent('is_not_client'),
            $id === Chat::INTENT_TALK_TO_AGENT => self::createIntent('talk_to_agent'),
            $id === Chat::INTENT_ASK_DOC_CATEGORIES => self::createIntent('ask_doc_categories'),
            Str::startsWith($id, Chat::INTENT_CHOOSE_DOC_CATEGORY_PREFIX) => self::createIntent('choose_doc_category', [
                'option_id' => $id,
            ]),
            default => self::createIntent('unknown_option', ['id' => $id]),
        };
    }

    private static function createIntent(string $name, array $data = []): object
    {
        return (object) [
            'name' => $name,
            'data' => $data,
        ];
    }
}
