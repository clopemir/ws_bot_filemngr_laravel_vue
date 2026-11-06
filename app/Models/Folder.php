<?php

namespace App\Models;

use Illuminate\Support\Collection;
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

    public static function boot()
    {
        parent::boot();

        static::creating(function ($folder) {
            $folder->slug = Str::slug($folder->folder_name);
            $folder->path = $folder->parent
                ? $folder->parent->path . '/' . $folder->slug
                : $folder->slug;

            // Una subcarpeta siempre pertenece al mismo cliente que su carpeta padre.
            $folder->client_id ??= $folder->parent?->client_id;
        });
    }

    public function parent(): BelongsTo {
        return $this->belongsTo(Folder::class, 'parent_id');
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

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Carpetas desde la raíz hasta el padre directo.
     *
     * @return Collection<int, Folder>
     */
    public function ancestors(): Collection
    {
        $ancestors = collect();
        $current = $this->parent;

        while ($current) {
            $ancestors->prepend($current);
            $current = $current->parent;
        }

        return $ancestors;
    }

    /**
     * Esta carpeta y todas las que contiene.
     *
     * @return Collection<int, Folder>
     */
    public function subtree(): Collection
    {
        $folders = collect([$this]);

        foreach ($this->children()->get() as $child) {
            $folders = $folders->concat($child->subtree());
        }

        return $folders;
    }

    /**
     * Cliente dueño. Revisa las carpetas superiores por si esta no tiene client_id.
     */
    public function owningClient(): ?Client
    {
        if ($this->client) {
            return $this->client;
        }

        return $this->ancestors()->reverse()->first(fn (Folder $folder) => $folder->client)?->client;
    }

    public function getStoragePath(): string
    {
        $names = $this->ancestors()->push($this)
            ->map(fn (Folder $folder) => Str::slug($folder->folder_name));

        return 'clientes/' . $names->implode('/');
    }
}
