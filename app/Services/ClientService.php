<?php

namespace App\Services;

use App\Models\Client;
use App\Support\Mask;
use App\Support\PhoneNumber;
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
        // Patrón para RFC (personas físicas y morales)
        $pattern = '/^([A-ZÑ&]{3,4}) ?(?:- ?)?(\d{2}(?:0[1-9]|1[0-2])(?:0[1-9]|[12]\d|3[01])) ?(?:- ?)?([A-Z\d]{2})([A\d])$/u';
        return (bool) preg_match($pattern, Str::upper($rfc));
    }

    /**
     * Normaliza un RFC al formato almacenado (minúsculas, sin espacios ni guiones).
     */
    public function normalizeRfc(string $rfc): string
    {
        return Str::lower(preg_replace('/[\s\-]+/', '', $rfc));
    }

    /**
     * Obtiene un cliente ACTIVO por su RFC.
     */
    public function getClientByRfc(string $rfc): ?Client
    {
        if (!$this->isValidRfcFormat($rfc)) {
            return null;
        }

        $client = Client::active()->where('client_rfc', $this->normalizeRfc($rfc))->first();

        Log::info($client ? 'Cliente encontrado por RFC.' : 'RFC sin cliente activo.', ['rfc' => Mask::rfc($rfc)]);

        return $client;
    }

    /**
     * ¿El número pertenece a algún cliente activo? Se usa para decidir qué tan
     * detallados pueden ser los mensajes de error sin permitir enumerar RFCs.
     */
    public function isRegisteredPhone(string $phone): bool
    {
        $lastDigits = substr(PhoneNumber::digits($phone), -10);

        return strlen($lastDigits) === 10
            && Client::active()->where('client_phone', 'like', "%{$lastDigits}")->exists();
    }
}
