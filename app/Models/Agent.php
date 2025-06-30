<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    protected $fillable = [
        'agent_name',
        'agent_lname',
        'agent_phone',
        'agent_mail',
        'agent_status'
    ];

    public static function boot()
{
    parent::boot();

    static::creating(function ($agent) {
        $agent->agent_phone = '52'.$agent->agent_phone;
    });
}

    public function clients(): HasMany {
        return $this->hasMany(Client::class);
    }
}
