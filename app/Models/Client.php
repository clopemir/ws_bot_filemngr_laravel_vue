<?php

namespace App\Models;

use App\Models\Folder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Client extends Model
{
    protected $fillable = [
        'agent_id',
        'client_name',
        'client_lname',
        'client_rfc',
        'client_phone',
        'client_mail',
        'client_status'
    ];

    public static function boot()
{
    parent::boot();

    static::creating(function ($client) {
        $client->wa_id = '521'.$client->client_phone;
    });
}

    public function agent(): BelongsTo {
        return $this->belongsTo(Agent::class);
    }

    public function folders(): HasMany {
        return $this->hasMany(Folder::class);
    }
}
