<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FileController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\WhatsApp\WaController;
use App\Http\Controllers\WhatsApp\SignedFileController;
use App\Http\Middleware\VerifyWhatsAppSignature;

Route::get('/', function () {

    //return redirect(route('login'));
    return Inertia::render('Home');
})->name('home');


// Para la verificación del webhook (GET)
Route::get('webhook', [WaController::class, 'verifyWebhook'])->middleware('throttle:30,1');
// Para recibir mensajes (POST): solo peticiones firmadas por Meta
Route::post('webhook', [WaController::class, 'receiveMessage'])->middleware(VerifyWhatsAppSignature::class);

// Descarga de documentos por WhatsApp: enlace firmado y temporal (ver File::temporaryDownloadUrl)
Route::get('wa/files/{file}', SignedFileController::class)
    ->middleware(['signed:relative', 'throttle:60,1'])
    ->name('whatsapp.files.download');

Route::middleware(['auth'])->group(function () {

    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('search', SearchController::class)->middleware('throttle:60,1')->name('search');

    Route::resource('clients', ClientController::class)->except('show');
    Route::resource('agents', AgentController::class)->except('show');
    Route::resource('folders', FolderController::class)->except(['create', 'edit']);

    Route::get('folder/{path}', [FolderController::class, 'showByPath'])
    ->where('path', '.*');
    Route::resource('files', FileController::class)->only(['store', 'show', 'destroy']);

});


require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
