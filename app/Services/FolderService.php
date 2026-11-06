<?php

namespace App\Services;

use App\Models\File;
use App\Models\Folder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class FolderService
{
    private const DISKS = ['local', 'public'];

    public function create(Folder $parent, string $name): Folder
    {
        $folder = Folder::create(['folder_name' => $name, 'parent_id' => $parent->id]);

        Storage::disk('local')->makeDirectory($folder->getStoragePath());

        return $folder;
    }

    /**
     * Renombra la carpeta, mueve sus archivos en disco y actualiza rutas y categoría,
     * porque la categoría que ve el bot es el nombre de la carpeta.
     */
    public function rename(Folder $folder, string $name): Folder
    {
        $oldBase = $folder->getStoragePath();
        $oldPath = $folder->path;
        $slug = Str::slug($name);
        $newPath = $folder->parent ? "{$folder->parent->path}/{$slug}" : $slug;
        $newBase = ($folder->parent ? $folder->parent->getStoragePath() . '/' : 'clientes/') . $slug;
        $subtree = $folder->subtree();
        $moved = [];

        try {
            DB::transaction(function () use ($folder, $name, $slug, $newPath, $oldPath, $oldBase, $newBase, $subtree, &$moved) {
                foreach ($subtree as $item) {
                    if ($item->isNot($folder)) {
                        $item->path = $newPath . Str::after($item->path, $oldPath);
                        $item->save();
                    }
                }

                $folder->forceFill(['folder_name' => $name, 'slug' => $slug, 'path' => $newPath])->save();

                File::where('folder_id', $folder->id)->update(['category' => $name]);

                if ($newBase === $oldBase) {
                    return;
                }

                foreach (File::whereIn('folder_id', $subtree->pluck('id'))->get() as $file) {
                    if (!Str::startsWith($file->file_path, "{$oldBase}/")) {
                        continue;
                    }

                    $target = $newBase . Str::after($file->file_path, $oldBase);

                    if ($disk = $file->storageDisk()) {
                        if (!Storage::disk($disk)->move($file->file_path, $target)) {
                            throw new RuntimeException("No se pudo mover el archivo {$file->id}.");
                        }
                        $moved[] = [$disk, $file->file_path, $target];
                    }

                    $file->update(['file_path' => $target]);
                }
            });
        } catch (Throwable $e) {
            // La base de datos ya se revirtió: los archivos también regresan a su lugar.
            foreach (array_reverse($moved) as [$disk, $from, $to]) {
                Storage::disk($disk)->move($to, $from);
            }

            throw $e;
        }

        if ($newBase !== $oldBase) {
            Storage::disk('local')->makeDirectory($newBase);
            $this->removeEmptyDirectory($oldBase);
        }

        return $folder->refresh();
    }

    /**
     * Borra la carpeta, sus subcarpetas y sus archivos. Devuelve cuántos archivos se borraron.
     */
    public function delete(Folder $folder): int
    {
        $base = $folder->getStoragePath();
        $folderIds = $folder->subtree()->pluck('id');
        $files = File::whereIn('folder_id', $folderIds)->get();

        // Se resuelve antes de borrar los registros: después ya no hay a quién preguntarle.
        $locations = $files->map(fn (File $file) => [$file->storageDisk(), $file->file_path]);

        DB::transaction(function () use ($folder, $folderIds) {
            File::whereIn('folder_id', $folderIds)->delete();
            $folder->delete(); // Las subcarpetas se eliminan en cascada.
        });

        foreach ($locations as [$disk, $path]) {
            if ($disk) {
                Storage::disk($disk)->delete($path);
            }
        }

        $this->removeEmptyDirectory($base);

        Log::channel('security')->info('Carpeta eliminada desde el panel.', [
            'folder_id' => $folder->id,
            'client_id' => $folder->client_id,
            'path' => $folder->path,
            'files' => $files->count(),
            'user_id' => auth()->id(),
        ]);

        return $files->count();
    }

    /**
     * Nunca borra un directorio que aún tenga archivos, aunque no estén registrados.
     */
    private function removeEmptyDirectory(string $path): void
    {
        foreach (self::DISKS as $disk) {
            if (Storage::disk($disk)->exists($path) && empty(Storage::disk($disk)->allFiles($path))) {
                Storage::disk($disk)->deleteDirectory($path);
            }
        }
    }
}
