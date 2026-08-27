<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TwilioMessageStatus extends Model
{
    protected $fillable = [
        'message_sid',
        'account_sid',
        'from',
        'to',
        'status',
        'error_code',
        'error_message',
        'payload',
        'last_callback_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'last_callback_at' => 'immutable_datetime',
        ];
    }
}
