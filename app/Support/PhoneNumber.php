<?php

namespace App\Support;

class PhoneNumber
{
    /**
     * Deja solo los dígitos de un número telefónico.
     */
    public static function digits(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone);
    }

    /**
     * Compara dos números usando sus últimos N dígitos (número nacional).
     * Así "5215512345678", "525512345678" y "5512345678" se consideran el mismo número.
     */
    public static function matches(?string $a, ?string $b): bool
    {
        $length = (int) config('whatsapp_bot.security.phone_match_digits', 10);
        $a = self::digits($a);
        $b = self::digits($b);

        if (strlen($a) < $length || strlen($b) < $length) {
            return false;
        }

        return hash_equals(substr($a, -$length), substr($b, -$length));
    }

    /**
     * Convierte un número nacional de 10 dígitos al formato que acepta la API de WhatsApp (52 + número).
     */
    public static function toWhatsApp(?string $phone): string
    {
        $digits = self::digits($phone);

        if (strlen($digits) === 10) {
            return '52' . $digits;
        }
        if (str_starts_with($digits, '521') && strlen($digits) === 13) {
            return '52' . substr($digits, 3);
        }

        return $digits;
    }
}
