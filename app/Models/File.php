<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class File extends Model
{
    protected $fillable = [
        'folder_id',
        'file_name',
        'original_file_name',
        'file_path',
        'file_size',
        'file_type'
    ];

    public function folder() : BelongsTo {
        return $this->belongsTo(Folder::class);
    }

    public function getUrl() {
       return Storage::disk('public')->url($this->file_path);
    }

}
