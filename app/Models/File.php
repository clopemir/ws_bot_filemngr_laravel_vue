<?php

namespace App\Models;

use App\Models\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
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

    public function getUrl() {
       return Storage::disk('public')->url($this->file_path);
    }

}
