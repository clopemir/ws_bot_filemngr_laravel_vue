<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class WhatsAppWebhookRequest extends FormRequest
{
    /**
     * La autenticidad la garantiza el middleware VerifyWhatsAppSignature.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Solo se exige la estructura mínima: Meta también envía notificaciones de estado
     * (enviado, entregado, leído) que no traen 'messages' y deben responderse con 200.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'entry' => ['required', 'array'],
            'entry.*.changes' => ['required', 'array'],
        ];
    }

    /**
     * Responder JSON 400 en lugar de redirigir (es una API, no un formulario).
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json(['status' => 'invalid_payload'], 400));
    }
}
