<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class TwilioMessageAttempt extends Model
{
    private const IMMUTABLE_ATTRIBUTES = [
        'twilio_campaign_recipient_id',
        'attempt_number',
        'idempotency_key',
        'submitted_at',
    ];

    protected $fillable = [
        ...self::IMMUTABLE_ATTRIBUTES,
        'message_sid',
        'status',
        'segments',
        'price',
        'price_unit',
        'provider_error_code',
        'provider_error_message',
        'provider_response',
        'accepted_at',
        'last_callback_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'segments' => 'integer',
            'price' => 'decimal:6',
            'provider_error_message' => 'encrypted',
            'provider_response' => 'encrypted:array',
            'submitted_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'last_callback_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $attempt): void {
            if ($attempt->isDirty(self::IMMUTABLE_ATTRIBUTES)) {
                throw new LogicException('The identity of a Twilio send attempt cannot be changed.');
            }

            if ($attempt->getOriginal('message_sid') !== null && $attempt->isDirty('message_sid')) {
                throw new LogicException('An accepted Twilio MessageSid cannot be changed.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('A Twilio send attempt cannot be deleted.');
        });
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(TwilioCampaignRecipient::class, 'twilio_campaign_recipient_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(TwilioStatusEvent::class, 'twilio_message_attempt_id');
    }
}
