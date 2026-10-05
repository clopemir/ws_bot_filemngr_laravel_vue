<?php

namespace App\Models;

use App\Models\Folder;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
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

    protected $casts = [
        'client_status' => 'boolean',
    ];

    public static function boot()
{
    parent::boot();

    static::saving(function ($client) {
        // Mantiene wa_id sincronizado si el teléfono cambia desde el panel.
        if ($client->isDirty('client_phone') || !$client->wa_id) {
            $client->wa_id = '521'.PhoneNumber::digits($client->client_phone);
        }
    });
}

    public function agent(): BelongsTo {
        return $this->belongsTo(Agent::class);
    }

    public function folders(): HasMany {
        return $this->hasMany(Folder::class);
    }

    public function files(): HasMany {
        return $this->hasMany(File::class, 'client_rfc', 'client_rfc');
    }

    public function scopeActive(Builder $query): Builder {
        return $query->where('client_status', true);
    }

    /**
     * Primer factor: ¿el número que escribe es el teléfono registrado del cliente?
     */
    public function ownsPhone(?string $phone): bool {
        // Solo client_phone: es el dato que el despacho administra desde el panel.
        return PhoneNumber::matches($this->client_phone, $phone);
    }
}
