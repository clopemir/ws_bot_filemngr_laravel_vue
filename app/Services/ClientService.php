<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ClientService
{
    /**
     * Verifica si un RFC tiene un formato válido.
     * No valida contra el SAT, solo el patrón.
     */
    public function isValidRfcFormat(string $rfc): bool
    {
        // Patrón mejorado para RFC (personas físicas y morales)
        $pattern = '/^([A-ZÑ&]{3,4}) ?(?:- ?)?(\d{2}(?:0[1-9]|1[0-2])(?:0[1-9]|[12]\d|3[01])) ?(?:- ?)?([A-Z\d]{2})([A\d])$/';
        return (bool) preg_match($pattern, Str::upper($rfc));
    }

    /**
     * Obtiene un cliente por su RFC.
     */
    public function getClientByRfc(string $rfc): ?Client
    {
        if (!$this->isValidRfcFormat(Str::upper($rfc))) {
            Log::info("Attempt to get client with invalid RFC format: {$rfc}");
            return null;
        }
        $client = Client::where('client_rfc', Str::lower(Str::squish($rfc)))->first();
        if ($client) {
            Log::info("Client found by RFC {$rfc}: {$client->client_name}");
        } else {
            Log::info("Client not found for RFC {$rfc}");
        }
        return $client;
    }

    /**
     * Intenta enlazar un chat de WhatsApp con un cliente existente usando el wa_id.
     * Opcionalmente, crea/actualiza el wa_id del cliente si se encuentra por RFC.
     */
    public function linkChatToClientByWaId(string $waId, string $clientRfc = null): ?Client
    {
        $client = Client::where('wa_id', $waId)->first();
        if ($client) {
            return $client;
        }

        // Si no se encuentra por wa_id y se proporciona un RFC, intentar buscar por RFC y actualizar wa_id
        if ($clientRfc) {
            $clientByRfc = $this->getClientByRfc($clientRfc);
            if ($clientByRfc) {
                $clientByRfc->wa_id = $waId;
                $clientByRfc->save();
                Log::info("Client {$clientByRfc->client_name} (RFC: {$clientRfc}) linked to wa_id {$waId}.");
                return $clientByRfc;
            }
        }
        return null;
    }
}
