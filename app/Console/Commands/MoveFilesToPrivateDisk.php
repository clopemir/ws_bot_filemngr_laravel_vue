<?php

namespace App\Console\Commands;

use App\Models\File;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Mueve los documentos subidos antes de esta versión desde storage/app/public
 * (accesibles por URL pública) a storage/app/private.
 */
class MoveFilesToPrivateDisk extends Command
{
    protected $signature = 'files:make-private {--dry-run : Solo muestra lo que se movería}';

    protected $description = 'Mueve los documentos de clientes del disco público al disco privado';

    public function handle(): int
    {
        $public = Storage::disk('public');
        $private = Storage::disk('local');
        $moved = $missing = $skipped = 0;

        File::query()->orderBy('id')->each(function (File $file) use ($public, $private, &$moved, &$missing, &$skipped) {
            if ($private->exists($file->file_path)) {
                $skipped++;
                return;
            }

            if (!$public->exists($file->file_path)) {
                $this->warn("No existe en ningún disco: [{$file->id}] {$file->file_path}");
                $missing++;
                return;
            }

            if (!$this->option('dry-run')) {
                $private->writeStream($file->file_path, $public->readStream($file->file_path));

                if ($private->size($file->file_path) !== $public->size($file->file_path)) {
                    $this->error("Copia incompleta, se conserva el original: [{$file->id}] {$file->file_path}");
                    $private->delete($file->file_path);
                    return;
                }

                $public->delete($file->file_path);
            }

            $moved++;
        });

        // Elimina la carpeta pública de clientes si quedó vacía.
        if (!$this->option('dry-run') && $public->exists('clientes') && empty($public->allFiles('clientes'))) {
            $public->deleteDirectory('clientes');
        }

        $this->info(($this->option('dry-run') ? '[Simulación] ' : '') . "Movidos: {$moved} · Ya privados: {$skipped} · Faltantes: {$missing}");

        return self::SUCCESS;
    }
}
