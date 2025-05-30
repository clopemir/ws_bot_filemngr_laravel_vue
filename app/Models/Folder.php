<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Folder extends Model
{
    protected $fillable = [
        'folder_name',
        'client_id',
        'parent_id'
    ];


    public function parent(): HasMany {
        return $this->hasMany(Folder::class, 'parent_id');
    }

    public function children(): HasMany {
        return $this->hasMany(Folder::class, 'parent_id');
    }

    public function files() : HasMany {
        return $this->hasMany(File::class);
    }

    public function client() : BelongsTo {
        return $this->belongsTo(Client::class);
    }

    public function getStoragePath(): string
    {
        $pathParts = [];
        $currentFolder = $this;

        while ($currentFolder) {
            // Es buena práctica "slugificar" los nombres de carpeta para rutas de archivo
            // si no lo haces ya al crear/nombrar la carpeta.
            array_unshift($pathParts, Str::slug($currentFolder->folder_name));
            //array_unshift($pathParts, $currentFolder->folder_name);
            if ($currentFolder->parent_id) {
                // Cuidado con N+1 aquí si no se carga la relación 'parent' ansiosamente.
                // Para este uso específico de ir subiendo uno a uno, find() está bien.
                $currentFolder = Folder::find($currentFolder->parent_id);
            } else {
                $currentFolder = null;
            }
        }
        return 'clientes/' . implode('/', $pathParts);
    }
}
