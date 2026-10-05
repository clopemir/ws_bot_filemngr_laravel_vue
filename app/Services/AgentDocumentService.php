<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Client;
use App\Models\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AgentDocumentService
{
    /**
     * Busca documentos de un cliente por nombre y tipo, SOLO entre los clientes asignados al agente.
     *
     * @return array{status: string, message: string, files?: \Illuminate\Support\Collection<int, File>}
     */
    public function findDocumentByClientAndType(Agent $agent, string $clientName, string $docName): array
    {
        // Un agente tiene pocos clientes: se compara el nombre completo en PHP (igual en MySQL y SQLite).
        $needle = Str::lower(Str::squish($clientName));
        $client = $agent->clients()->get()
            ->first(fn (Client $client) => Str::contains(Str::lower("{$client->client_name} {$client->client_lname}"), $needle));

        if (!$client) {
            return ['status' => 'error', 'message' => 'No encontré entre tus clientes asignados a: ' . $clientName];
        }

        $documents = File::where('client_rfc', $client->client_rfc)
            ->where('category', 'LIKE', $this->escapeLike($docName) . '%')
            ->latest()
            ->limit(5)
            ->get();

        Log::channel('security')->info('Búsqueda de documentos por agente.', [
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'category' => $docName,
            'count' => $documents->count(),
        ]);

        if ($documents->isEmpty()) {
            return ['status' => 'error', 'message' => 'No se encontraron documentos para el cliente: ' . $client->client_name . ' ' . $client->client_lname];
        }

        return [
            'status' => 'success',
            'message' => 'Documentos encontrados para el cliente: ' . $client->client_name . ' ' . $client->client_lname,
            'files' => $documents,
        ];
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }
}
