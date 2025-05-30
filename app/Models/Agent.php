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

    public function clients(): HasMany {
        return $this->hasMany(Client::class);
    }
}
