<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationCode extends Model
{
    use Prunable;

    protected $fillable = [
        'chat_id',
        'client_id',
        'requester_phone',
        'code_hash',
        'channels',
        'attempts',
        'expires_at',
        'consumed_at',
    ];

    protected $hidden = ['code_hash'];

    protected $casts = [
        'channels' => 'array',
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('consumed_at')->where('expires_at', '>', now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Los códigos se conservan 30 días como bitácora y luego se eliminan (`php artisan model:prune`).
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(30));
    }
}
