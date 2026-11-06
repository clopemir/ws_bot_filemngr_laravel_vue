<?php

return [
    // Orden de uso: si uno falla, se queda sin cupo o está en pausa, se intenta el siguiente.
    'providers' => array_values(array_filter(array_map('trim', explode(',', (string) env('AI_PROVIDERS', 'gemini,openrouter'))))),

    // Topes propios para mantenerse en la capa gratuita. Un valor de 0 desactiva al proveedor.
    'limits' => [
        'gemini' => [
            'per_minute' => (int) env('GEMINI_MAX_CALLS_PER_MINUTE', 10),
            'per_day' => (int) env('GEMINI_MAX_CALLS_PER_DAY', 200),
        ],
        // OpenRouter permite 20 por minuto y 50 al día sin crédito comprado: se deja margen.
        'openrouter' => [
            'per_minute' => (int) env('OPENROUTER_MAX_CALLS_PER_MINUTE', 15),
            'per_day' => (int) env('OPENROUTER_MAX_CALLS_PER_DAY', 45),
        ],
    ],

    // Pausa tras un 429 o 402 cuando el proveedor no indica cuánto esperar.
    'cooldown_seconds' => (int) env('AI_COOLDOWN_SECONDS', 60),
];
