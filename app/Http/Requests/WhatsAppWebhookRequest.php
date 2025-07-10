<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WhatsAppWebhookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'entry' => ['required', 'array'],
            'entry.*.changes' => ['required', 'array'],
            'entry.*.changes.*.value.messages' => ['required', 'array'],
            'entry.*.changes.*.value.contacts' => ['required', 'array'],
        ];
    }
}
