<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class TwilioCampaignRecipient extends Model
{
    private const IMMUTABLE_ATTRIBUTES = [
        'twilio_campaign_id',
        'ordinal',
        'source_row',
        'identity_hash',
        'role',
        'phone',
        'phone_hash',
        'body',
        'body_hash',
        'segments',
        'estimated_cost',
        'idempotency_key',
        'exclusion_reason',
    ];

    protected $fillable = [
        ...self::IMMUTABLE_ATTRIBUTES,
        'status',
    ];

    protected function casts(): array
    {
        return [
            'phone' => 'encrypted',
            'body' => 'encrypted',
            'segments' => 'integer',
            'estimated_cost' => 'decimal:6',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $recipient): void {
            if ($recipient->isDirty(self::IMMUTABLE_ATTRIBUTES)) {
                throw new LogicException('A sealed Twilio recipient manifest entry cannot be changed.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('A sealed Twilio recipient manifest entry cannot be deleted.');
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(TwilioCampaign::class, 'twilio_campaign_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(TwilioMessageAttempt::class, 'twilio_campaign_recipient_id');
    }
}
