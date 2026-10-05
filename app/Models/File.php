<?php

namespace App\Models;

use App\Models\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class File extends Model
{
    protected $fillable = [
        'folder_id',
        'file_name',
        'original_file_name',
        'file_path',
        'file_size',
        'file_type',
        'client_rfc',
        'category'
    ];

    public function client(): BelongsTo {
        return $this->belongsTo(Client::class, 'client_rfc');
    }

    public function folder() : BelongsTo {
        return $this->belongsTo(Folder::class);
    }

    /**
     * Disco donde vive el archivo. Los archivos nuevos se guardan en el disco privado ('local');
     * los antiguos pueden seguir en 'public' hasta ejecutar `php artisan files:make-private`.
     */
    public function storageDisk(): ?string {
        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($this->file_path)) {
                return $disk;
            }
        }

        return null;
    }

    /**
     * Enlace temporal y firmado para que WhatsApp descargue el archivo.
     * Caduca a los pocos minutos y no puede alterarse sin invalidar la firma.
     */
    public function temporaryDownloadUrl(): string {
        // Firma relativa: valida ruta + parámetros, no el esquema ni el host. Así un proxy que
        // entregue la petición como http:// (o con otro host interno) no invalida el enlace.
        $path = URL::temporarySignedRoute(
            'whatsapp.files.download',
            now()->addMinutes((int) config('whatsapp_bot.security.download_link_ttl_minutes', 10)),
            ['file' => $this->id],
            absolute: false
        );

        return rtrim((string) config('app.url'), '/') . $path;
    }

}
