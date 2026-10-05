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
            // Notificaciones de estado (enviado/entregado/leído/fallido).
            self::logFailedStatuses($value['statuses'] ?? []);
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

    /**
     * Meta avisa por webhook cuando un mensaje aceptado no se pudo entregar
     * (p. ej. no pudo descargar un documento). Sin esto el fallo sería invisible.
     */
    private static function logFailedStatuses(array $statuses): void
    {
        foreach ($statuses as $status) {
            if (($status['status'] ?? null) !== 'failed') {
                continue;
            }

            Log::warning('WhatsApp no pudo entregar un mensaje.', [
                'message_id' => $status['id'] ?? null,
                'errors' => collect($status['errors'] ?? [])->map(fn ($e) => [
                    'code' => $e['code'] ?? null,
                    'title' => $e['title'] ?? null,
                    'details' => $e['error_data']['details'] ?? null,
                ])->all(),
            ]);
        }
    }

    private static function extractUserMessage(array $messageData, string $type): ?string
    {
        if ($type === 'interactive') {
            return $messageData['interactive']['button_reply']['id']
                ?? $messageData['interactive']['list_reply']['id']
                ?? null;
        }
        if ($type === 'text') {
            $body = $messageData['text']['body'] ?? null;
            // Limitar el tamaño evita abusos (contexto en BD y prompts a la IA).
            return $body === null ? null : Str::limit($body, 1000, '');
        }
        // No soportamos otros tipos como 'image', 'audio', etc. por ahora.
        return null;
    }
}
