<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class TwilioStatusEvent extends Model
{
    protected $fillable = [
        'twilio_message_attempt_id',
        'message_sid',
        'event_hash',
        'source',
        'status',
        'previous_status',
        'applied',
        'error_code',
        'error_message',
        'payload',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'applied' => 'boolean',
            'error_message' => 'encrypted',
            'payload' => 'encrypted:array',
            'received_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $event): void {
            $changed = array_keys($event->getDirty());
            $linkageFields = [
                'twilio_message_attempt_id',
                'previous_status',
                'applied',
                'updated_at',
            ];

            if ($event->getOriginal('twilio_message_attempt_id') !== null
                || array_diff($changed, $linkageFields) !== []) {
                throw new LogicException('The captured content of a Twilio status event is immutable.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('A Twilio status event cannot be deleted.');
        });
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(TwilioMessageAttempt::class, 'twilio_message_attempt_id');
    }
}
