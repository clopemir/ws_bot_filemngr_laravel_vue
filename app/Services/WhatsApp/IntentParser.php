<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Str;

class IntentParser
{
    /**
     * Parsea el mensaje/ID del usuario para determinar su intención.
     * @return object
     */
    public static function parse(string $userMessage): object
    {
        $userMessageLower = Str::lower($userMessage);

        // Comandos globales
        if (in_array($userMessageLower, ['cancelar', 'menu', 'menú', 'reset'])) {
            return self::createIntent('reset');
        } else if (Str::contains($userMessageLower, ['gracias', 'adiós', 'adios', 'hasta luego', 'bye', 'nada más', 'es todo', 'no necesito nada más', 'no necesito nada'])) {
            return self::createIntent('end_conversation');
        }

        // Intenciones de botones simples
        if ($userMessage === 'client') return self::createIntent('is_client');
        if ($userMessage === 'no_client') return self::createIntent('is_not_client');
        if ($userMessage === 'agent_chat') return self::createIntent('talk_to_agent');

        // Intenciones complejas con datos (ej. 'ask_doc_cat_XAXX010101000')
        if (Str::startsWith($userMessage, 'ask_doc_cat_')) {
            return self::createIntent('ask_doc_categories', [
                'rfc' => Str::after($userMessage, 'ask_doc_cat_')
            ]);
        }
        if (Str::startsWith($userMessage, 'cho_doc_cat_')) {
            $parts = explode('_', Str::after($userMessage, 'cho_doc_cat_'));
            $rfc = array_pop($parts); // El RFC es siempre el último elemento
            $category = implode('_', $parts); // La categoría puede contener guiones bajos
            return self::createIntent('choose_doc_category', [
                'category' => $category,
                'rfc' => $rfc
            ]);
        }

        // Si no coincide nada, es una intención de texto genérica
        return self::createIntent('text_input', ['text' => $userMessage]);
    }

    private static function createIntent(string $name, array $data = []): object
    {
        return (object) [
            'name' => $name,
            'data' => $data,
            'is' => fn(string $intentName) => $name === $intentName,
        ];
    }
}
