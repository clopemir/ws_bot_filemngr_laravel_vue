<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Client;
use App\Models\File;
use App\Services\Ai\AiGateway;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(AiGateway $ai): Response
    {
        return Inertia::render('Dashboard', [
            'stats' => [
                'clients' => Client::count(),
                'activeClients' => Client::active()->count(),
                'agents' => Agent::where('agent_status', true)->count(),
                'files' => File::count(),
                'filesThisWeek' => File::where('created_at', '>=', now()->subDays(7))->count(),
                'storageBytes' => (int) File::sum('file_size'),
                'clientsWithoutFiles' => Client::doesntHave('files')->count(),
            ],
            'recentFiles' => File::with('folder:id,folder_name,path,client_id', 'folder.client:id,client_name,client_lname')
                ->latest()
                ->limit(6)
                ->get(['id', 'folder_id', 'original_file_name', 'category', 'created_at']),
            'aiUsage' => $ai->usage(),
        ]);
    }
}
