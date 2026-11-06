<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Client;
use App\Models\File;
use App\Models\Folder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Búsqueda global del panel: clientes, agentes, carpetas y archivos.
 */
class SearchController extends Controller
{
    private const LIMIT = 5;

    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q'));

        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $like = '%' . addcslashes($term, '\\%_') . '%';

        $clients = Client::query()
            ->where(fn ($query) => $query
                ->where('client_name', 'like', $like)
                ->orWhere('client_lname', 'like', $like)
                ->orWhere('client_rfc', 'like', $like))
            ->with(['folders' => fn ($query) => $query->whereNull('parent_id')])
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Client $client) => [
                'type' => 'Cliente',
                'title' => trim("{$client->client_name} {$client->client_lname}"),
                'subtitle' => strtoupper($client->client_rfc),
                'href' => $client->folders->first()
                    ? "/folder/{$client->folders->first()->path}"
                    : "/clients/{$client->id}/edit",
            ]);

        $agents = Agent::query()
            ->where(fn ($query) => $query
                ->where('agent_name', 'like', $like)
                ->orWhere('agent_lname', 'like', $like))
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Agent $agent) => [
                'type' => 'Agente',
                'title' => trim("{$agent->agent_name} {$agent->agent_lname}"),
                'subtitle' => $agent->agent_mail,
                'href' => "/agents/{$agent->id}/edit",
            ]);

        $folders = Folder::query()
            ->whereNotNull('parent_id')
            ->where('folder_name', 'like', $like)
            ->with('client:id,client_name,client_lname')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Folder $folder) => [
                'type' => 'Carpeta',
                'title' => $folder->folder_name,
                'subtitle' => trim(($folder->client?->client_name ?? '') . ' ' . ($folder->client?->client_lname ?? '')),
                'href' => "/folder/{$folder->path}",
            ]);

        $files = File::query()
            ->where('original_file_name', 'like', $like)
            ->with('folder:id,path,folder_name')
            ->latest()
            ->limit(self::LIMIT)
            ->get()
            ->filter(fn (File $file) => $file->folder)
            ->map(fn (File $file) => [
                'type' => 'Archivo',
                'title' => $file->original_file_name,
                'subtitle' => $file->folder->folder_name,
                'href' => "/folder/{$file->folder->path}",
            ]);

        return response()->json([
            'results' => $clients->concat($agents)->concat($folders)->concat($files)->values(),
        ]);
    }
}
