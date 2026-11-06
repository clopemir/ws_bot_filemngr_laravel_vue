<?php

namespace App\Services\Ai;

use RuntimeException;

class AiProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message);
    }

    /**
     * Sin cupo o sin crédito: no tiene caso volver a intentar de inmediato.
     */
    public function shouldPauseProvider(): bool
    {
        return in_array($this->status, [402, 429], true);
    }
}
