<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsSuppression extends Model
{
    protected $fillable = [
        'phone_hash',
        'phone_e164',
        'source',
        'reason',
        'first_suppressed_at',
        'last_suppressed_at',
    ];

    protected function casts(): array
    {
        return [
            'phone_e164' => 'encrypted',
            'first_suppressed_at' => 'immutable_datetime',
            'last_suppressed_at' => 'immutable_datetime',
        ];
    }
}
