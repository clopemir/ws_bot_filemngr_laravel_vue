<?php

namespace App\Services\Ai;

interface AiProvider
{
    public function name(): string;

    public function isConfigured(): bool;

    /**
     * @param  array<int, array{role: string, content: string}>  $messages  Roles: user o assistant.
     * @param  array{temperature?: float, max_tokens?: int, json?: bool}  $options
     *
     * @throws AiProviderException
     */
    public function complete(string $system, array $messages, array $options = []): string;
}
