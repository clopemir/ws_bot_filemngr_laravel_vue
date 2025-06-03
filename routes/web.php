<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FileController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\FolderController;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');


Route::middleware(['auth'])->group(function () {

    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::resource('clients', ClientController::class)->except('show');
    Route::resource('agents', AgentController::class)->except('show');
    Route::resource('folders', FolderController::class);
    Route::get('{path}', [FolderController::class, 'showByPath'])
    ->where('path', '.*');
    //->name('folders.show');
    Route::resource('files', FileController::class);

});


require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
