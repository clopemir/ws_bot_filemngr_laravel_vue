<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Agent;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    public function index() {

        $agents = Agent::latest()->paginate(10);

        return Inertia::render('Agents/Index', [
            'agents' => $agents
        ]);
    }

    public function create() {

        return Inertia::render('Agents/Create');
    }

    public function store(Request $request) {

        $validated = $request->validate([
            'agent_name' => 'required|string|max:225',
            'agent_lname' => 'string|max:225',
            'agent_phone' => 'required|string|max:10',
            'agent_mail' => 'email|max:100|unique:agents,agent_mail'
        ],[
            'agent_mail.unique' => 'El correo ya está registrado en el sistema.',
        ]);


        try {

            Agent::create($validated);

            return redirect()->route('agents.index')->with('success','Agente Creado!');

        } catch (\Throwable $th) {
            throw $th;
            return redirect(route('agents.index'));
        }



        //Folder::create($validated['client_rfc']);

    }

    public function edit(Agent $agent) {

        return Inertia::render('Agents/Edit', [
            'agent' => $agent,
        ]);
    }

    public function update(Request $request, Agent $agent) {

        $validated = $request->validate([
            'agent_name' => 'required|string|max:225',
            'agent_lname' => 'string|max:225',
            'agent_status' => 'boolean|required'
        ]);

        $agent->update($validated);

        return redirect()->route('agents.index')->with('success', 'Agente Actualizado Correctamente');
    }

    public function destroy(Agent $agent) {


        if ($agent->clients()->exists()) {
            return back()->withErrors(['agent' => 'No se puede eliminar un agente con empresas relacionadas']);
        }
        $agent->delete();

        return redirect()->route('agents.index')->with('success', 'Agente eliminado con éxito.');
    }
}
