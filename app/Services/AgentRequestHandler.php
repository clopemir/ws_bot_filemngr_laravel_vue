<?php

namespace App\Services;

use App\Jobs\SendAgentDocumentsJob;
use App\Models\Agent;
use App\Models\Chat;
use App\Models\Client;
use App\Services\Ai\AgentAssistant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Atiende los mensajes de los agentes. Primero intenta resolverlos sin IA, para cuidar el cupo gratuito
 * y no enviar datos de clientes a terceros cuando no hace falta.
 */
class AgentRequestHandler
{
    private const MEMORY_MINUTES = 10;
    private const MAX_OPTIONS = 5;

    public function __construct(
        private WhatsAppService $whatsAppService,
        private AgentDocumentService $documents,
        private AgentAssistant $assistant,
    ) {
    }

    public function handle(Agent $agent, string $message, Chat $chat, object $payload): void
    {
        $memory = Cache::get($this->memoryKey($agent), []);

        // Respuesta a una lista de clientes con el mismo nombre.
        if (preg_match('/^\s*(\d{1,2})\s*$/', $message, $choice) && !empty($memory['options'])) {
            $client = Client::find($memory['options'][(int) $choice[1] - 1] ?? 0);

            if ($client) {
                $this->respondFor($agent, collect([$client]), $memory['term'] ?? null);
                return;
            }
        }

        $parsed = $this->documents->parse($message);
        $term = $parsed['term'] ?? null;

        // Identificado sin IA: "constancia de Juan Pérez", o el cliente o documento que faltaba.
        if ($parsed['clients']->isNotEmpty() && ($term || $parsed['wants_documents'] || !empty($memory['term']))) {
            $this->respondFor($agent, $parsed['clients'], $term ?? ($memory['term'] ?? null));
            return;
        }

        // Continúa con el último cliente: "¿y la opinión?".
        if ($term && !$parsed['has_other_words'] && !empty($memory['client_id']) && ($client = Client::find($memory['client_id']))) {
            $this->respondFor($agent, collect([$client]), $term);
            return;
        }

        $this->whatsAppService->sendTypingIndicator($payload->messageId);

        $interpreted = $this->assistant->interpret($message);

        if ($interpreted === null) {
            $this->say($agent, trans($parsed['wants_documents'] ? 'whatsapp.agent.ai_unavailable_documents' : 'whatsapp.agent.ai_unavailable'));
            return;
        }

        if ($interpreted['intent'] === 'fetch_document') {
            $this->handleInterpretedRequest($agent, $interpreted, $term, $memory);
            return;
        }

        $this->converse($agent, $message, $chat, $payload);
    }

    private function handleInterpretedRequest(Agent $agent, array $interpreted, ?string $localTerm, array $memory): void
    {
        $term = $interpreted['document_name'] ?? $localTerm ?? ($memory['term'] ?? null);

        if ($interpreted['client_name']) {
            $clients = $this->documents->findClients($interpreted['client_name']);

            if ($clients->isEmpty()) {
                $this->remember($agent, ['term' => $term]);
                $this->say($agent, trans('whatsapp.agent.client_not_found', ['name' => $interpreted['client_name']]));
                return;
            }

            $this->respondFor($agent, $clients, $term);
            return;
        }

        $client = !empty($memory['client_id']) ? Client::find($memory['client_id']) : null;

        if ($client) {
            $this->respondFor($agent, collect([$client]), $term);
            return;
        }

        $this->remember($agent, ['term' => $term]);
        $this->say($agent, trans('whatsapp.agent.which_client'));
    }

    /**
     * @param  Collection<int, Client>  $clients
     */
    private function respondFor(Agent $agent, Collection $clients, ?string $term): void
    {
        if ($clients->count() > 1) {
            $options = $clients->take(self::MAX_OPTIONS)->values();
            $this->remember($agent, ['options' => $options->pluck('id')->all(), 'term' => $term]);

            $list = $options->map(fn (Client $client, int $index) => ($index + 1) . '. ' . $this->clientLabel($client) . ' (' . strtoupper($client->client_rfc) . ')')->implode("\n");
            $this->say($agent, trans('whatsapp.agent.choose_client', ['list' => $list]));
            return;
        }

        $client = $clients->first();

        if (!$term) {
            $this->remember($agent, ['client_id' => $client->id]);
            $categories = $this->documents->categoriesFor($client);

            $this->say($agent, $categories === []
                ? trans('whatsapp.agent.no_documents', ['client' => $this->clientLabel($client)])
                : trans('whatsapp.agent.which_document', ['client' => $this->clientLabel($client), 'categories' => $this->bulletList($categories)]));
            return;
        }

        $result = $this->documents->findFiles($client, $term);
        $this->remember($agent, ['client_id' => $client->id]);

        if ($result['files']->isEmpty()) {
            $categories = $this->documents->categoriesFor($client);

            $this->say($agent, $categories === []
                ? trans('whatsapp.agent.no_documents', ['client' => $this->clientLabel($client)])
                : trans('whatsapp.agent.document_not_found', ['term' => $term, 'client' => $this->clientLabel($client), 'categories' => $this->bulletList($categories)]));
            return;
        }

        $sending = $result['files']->count();
        $this->say($agent, $result['total'] > $sending
            ? trans('whatsapp.agent.sending_some', ['count' => $sending, 'total' => $result['total'], 'label' => $result['label'], 'client' => $this->clientLabel($client)])
            : trans('whatsapp.agent.sending', ['count' => $sending, 'label' => $result['label'], 'client' => $this->clientLabel($client)]));

        SendAgentDocumentsJob::dispatch($agent->id, $client->id, $result['files']->pluck('id')->all(), $result['label']);
    }

    private function converse(Agent $agent, string $message, Chat $chat, object $payload): void
    {
        $chat->update(['action' => Chat::ACTION_IA_CONVERSATION]);

        $reply = $this->assistant->chat(str_replace(["\n", "\r"], ' ', $message), $payload->userName, $chat->context ?? []);

        if ($reply === null) {
            $this->say($agent, trans('whatsapp.agent.ai_unavailable'));
            return;
        }

        $this->say($agent, $reply);
        $chat->addMessageToContext($reply, 'assistant');
    }

    private function say(Agent $agent, string $text): void
    {
        $this->whatsAppService->sendTextMessage($agent->agent_phone, $text);
    }

    private function clientLabel(Client $client): string
    {
        return trim("{$client->client_name} {$client->client_lname}");
    }

    /**
     * @param  string[]  $items
     */
    private function bulletList(array $items): string
    {
        return collect($items)->map(fn (string $item) => "• {$item}")->implode("\n");
    }

    private function remember(Agent $agent, array $memory): void
    {
        Cache::put($this->memoryKey($agent), $memory, now()->addMinutes(self::MEMORY_MINUTES));
    }

    private function memoryKey(Agent $agent): string
    {
        return "agent-request:{$agent->id}";
    }
}
