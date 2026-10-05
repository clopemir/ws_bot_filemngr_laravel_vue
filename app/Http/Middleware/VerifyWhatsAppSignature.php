<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Valida que el webhook realmente provenga de Meta comprobando la cabecera
 * X-Hub-Signature-256 (HMAC-SHA256 del cuerpo crudo con el App Secret).
 *
 * Sin esto, cualquiera podría enviar un POST a /webhook haciéndose pasar por
 * el número de un cliente o de un agente.
 */
class VerifyWhatsAppSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.whatsapp.app_secret');

        if (!$secret) {
            // Excepción explícita para desarrollo. No depende de APP_ENV: un servidor publicado
            // por error con APP_ENV=local seguiría protegido.
            if (config('services.whatsapp.allow_unsigned_webhooks') === true) {
                Log::warning('Webhook sin validar firma: WHATSAPP_ALLOW_UNSIGNED_WEBHOOKS está activo. Nunca usar en producción.');
                return $next($request);
            }

            Log::critical('WHATSAPP_APP_SECRET no está configurado: se rechazan los webhooks.');
            abort(Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $signature = (string) $request->header('X-Hub-Signature-256');
        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

        if (!hash_equals($expected, $signature)) {
            Log::channel('security')->warning('Webhook de WhatsApp con firma inválida.', ['ip' => $request->ip()]);
            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
