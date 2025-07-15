<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FileController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\WhatsApp\WaController;

Route::get('/', function () {

    //return redirect(route('login'));
    return Inertia::render('Home');
})->name('home');


// Para la verificación del webhook (GET)
Route::get('webhook', [WaController::class, 'verifyWebhook']);
// Para recibir mensajes (POST)
Route::post('webhook', [WaController::class, 'receiveMessage']);

// Ruta para el job de recordatorios (opcional, si quieres dispararlo manualmente o con cron externo)
// Route::get('/wa/send-reminders', [WaController::class, 'sendScheduledReminders'])->name('wa.sendReminders'); // Proteger esta ruta

Route::middleware(['auth'])->group(function () {

    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::resource('clients', ClientController::class)->except('show');
    Route::resource('agents', AgentController::class)->except('show');
    Route::resource('folders', FolderController::class);
    Route::get('folder/{folderPath?}/create', [FolderController::class, 'create'])
    ->where('folderPath', '.*');

    Route::get('folder/{path}', [FolderController::class, 'showByPath'])
    ->where('path', '.*');
    //->name('folders.show');
    Route::resource('files', FileController::class);

});


require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
