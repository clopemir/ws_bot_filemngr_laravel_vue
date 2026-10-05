<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Tareas programadas
|--------------------------------------------------------------------------
|
| En el servidor basta con UN cron cada minuto:
|   * * * * * cd /ruta/al/proyecto/current && php artisan schedule:run >> /dev/null 2>&1
|
*/

// Procesa la cola (envío de documentos y avisos a agentes) sin necesitar supervisor.
Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground();

// Elimina códigos de verificación antiguos.
Schedule::command('model:prune')->daily();
