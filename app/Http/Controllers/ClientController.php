<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Agent;
use App\Models\Client;
use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ClientController extends Controller
{
    public function index() {

        $clients = Client::with('agent')->latest()->paginate(10);

        return Inertia::render('Clients/Index', [
            'clients' => $clients
        ]);
    }

    public function create() {

        return Inertia::render('Clients/Create', [
            'agents' => Agent::orderBy('agent_name')->get()
        ]);
    }

    public function store(Request $request) {

        $validated = $request->validate([
            'agent_id' => 'required|exists:agents,id',
            'client_name' => 'required|string|max:225',
            'client_lname' => 'string|max:225',
            'client_rfc' => 'required|string|max:13|regex:/^([a-zA-ZñÑ&]{3,4})\d{6}(?:[a-zA-Z\d]{3})?$/|unique:clients,client_rfc',
            'client_phone' => 'required|string|max:10',
            'client_mail' => 'email|max:100|unique:clients,client_mail'
        ],[
            'client_rfc.unique' => 'El RFC ya está registrado en el sistema.',
            'client_mail.unique' => 'El mail ya está registrado en el sistema.'
        ]);


        try {

            $client = Client::create($validated);

            $rootFolder = Folder::create([
                'client_id' => $client->id,
                'folder_name' => $validated['client_rfc'],
                'parent_id' => null
            ]);

            $defaultSubfolders = ['constancias', 'opiniones', 'impuestos'];

            foreach ($defaultSubfolders as $subFolder) {
                Folder::create([
                    'client_id' => $client->id,
                    'folder_name' => $subFolder,
                    'parent_id' => $rootFolder->id
                ]);
            }

            $basePath = 'clientes/'.$validated['client_rfc'];

            Storage::disk('public')->makeDirectory($basePath);

            foreach ($defaultSubfolders as $subfolder) {
                Storage::disk('public')->makeDirectory("{$basePath}/{$subfolder}");
            }


            return redirect(route('clients.index'))->with('success', 'El Cliente ' . $validated['client_name'] . ', se ha creado correctamente!');

        } catch (\Throwable $th) {
            Log::error($th);;
            return redirect(route('clients.index'))->with('error', 'Ocurrio un error al crear el cliente');
        }

    }

    public function edit(Client $client) {

        return Inertia::render('Clients/Edit', [
            'client' => $client,
            'agents' => Agent::orderBy('agent_name')->get()
        ]);
    }

    public function update(Request $request, Client $client) {

        $validated = $request->validate([
            'agent_id' => 'required|exists:agents,id',
            'client_name' => 'required|string|max:225',
            'client_lname' => 'string|max:225',
            'client_status' => 'boolean|required'
        ]);
        $validated['client_rfc'] = strtolower($validated['client_rfc']);

        $client->update($validated);

        return redirect(route('clients.index'))->with('success', 'El Cliente ' . $validated['client_name'] . ', se ha actualizado correctamente!');
    }

    public function destroy(Client $client) {

        $client->delete();

        return redirect(route('clients.index'))->with('success', 'El Cliente se ha eliminado correctamente!');
    }
}

