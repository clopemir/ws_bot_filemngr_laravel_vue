<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Enmascara datos personales antes de escribirlos en logs o mostrarlos a terceros.
 */
class Mask
{
    public static function rfc(?string $rfc): string
    {
        $rfc = Str::upper((string) $rfc);

        return strlen($rfc) > 4 ? substr($rfc, 0, 4) . str_repeat('*', strlen($rfc) - 4) : '****';
    }

    public static function phone(?string $phone): string
    {
        $digits = PhoneNumber::digits($phone);

        return strlen($digits) >= 4 ? '***' . substr($digits, -4) : '****';
    }

    public static function email(?string $email): string
    {
        if (!$email || !str_contains($email, '@')) {
            return '****';
        }

        [$user, $domain] = explode('@', $email, 2);

        return substr($user, 0, 2) . str_repeat('*', max(strlen($user) - 2, 3)) . '@' . $domain;
    }
}
