<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    protected $fillable = [
        'wa_id',
        'user_name',
        'user_phone',
        'client_rfc',
        'client_id',
        'context',
        'user_intention',
        'action',
        'is_client',
    ];

    protected $casts = [
        'context' => 'array'
    ];
}
