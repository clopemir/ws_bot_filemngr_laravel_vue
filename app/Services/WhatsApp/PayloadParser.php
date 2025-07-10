<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PayloadParser
{
    /**
     * Parsea el payload de la request de WhatsApp a un objeto estandarizado.
     * @return object|null
     */
    public static function parse(Request $request): ?object
    {
        $value = $request->input('entry.0.changes.0.value');
        if (!isset($value['messages'][0]) || !isset($value['contacts'][0])) {
            Log::warning('Payload de webhook inválido o vacío.', ['value' => $value]);
            return null;
        }

        $messageData = $value['messages'][0];
        $contactData = $value['contacts'][0];
        $messageType = $messageData['type'];
        $userMessage = self::extractUserMessage($messageData, $messageType);

        if ($userMessage === null) {
            Log::info("Tipo de mensaje no soportado: {$messageType}");
            return null;
        }

        // Limpieza de formato de número regional
        $userPhone = $messageData['from'];
        if (Str::startsWith($userPhone, '521')) {
            $userPhone = '52' . substr($userPhone, 3);
        }

        return (object) [
            'waId' => $contactData['wa_id'],
            'userName' => $contactData['profile']['name'] ?? 'Usuario',
            'userPhone' => $userPhone,
            'messageId' => $messageData['id'],
            'messageType' => $messageType,
            'userMessage' => $userMessage,
        ];
    }

    private static function extractUserMessage(array $messageData, string $type): ?string
    {
        if ($type === 'interactive') {
            return $messageData['interactive']['button_reply']['id']
                ?? $messageData['interactive']['list_reply']['id']
                ?? null;
        }
        if ($type === 'text') {
            return $messageData['text']['body'] ?? null;
        }
        // No soportamos otros tipos como 'image', 'audio', etc. por ahora.
        return null;
    }
}
