<?php

namespace App\Services;

use App\Models\File;
use App\Models\Agent;
use App\Models\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;


class AgentDocumentService
{
    public function findDocumentByClientAndType(string $clientName, string $docName)
    {
        $client = Client::whereRaw('LOWER(client_name) LIKE ?', ['%' . strtolower($clientName) . '%'])
                        ->first();


        if(!$client) {
            return ['status' => 'error', 'message' => 'No hay coincidencias con este nombre de cliente: ' . $clientName];
        }

        $documents = File::where('client_rfc', $client->client_rfc)
                        ->where('category', 'LIKE', $docName . '%')
                        ->limit(5)
                        ->get();

        // loggear si encontro documentos
        Log::info('Búsqueda de documentos para el cliente: ' . $client->name . ' y tipo: ' . $docName . '. Documentos encontrados: ' . $documents->count());

        if($documents->isEmpty()) {
            return ['status' => 'error', 'message' => 'No se encontraron documentos para el cliente: ' . $client->client_name . ' ' . $client->client_lname];
        }

        $files = $documents->map(function($doc) {
            return [
                'file_name' => $doc->original_file_name,
                'file_url' => Storage::disk('public')->url($doc->file_path)
            ];
        })->all();

        //loguear los detalles de cada archivo
        Log::info('Documentos encontrados:', $files);

        return [
            'status' => 'success',
            'message' => 'Documentos encontrados para el cliente: ' . $client->client_name . ' ' . $client->client_lname,
            'files' => $files
        ];
    }
}