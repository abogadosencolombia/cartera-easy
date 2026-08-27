<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class TwilioCampaign extends Model
{
    protected $fillable = [
        'campaign_key',
        'name',
        'source_reference',
        'manifest',
        'manifest_sha256',
        'raw_manifest_sha256',
        'rne_proof_sha256',
        'plan_sha256',
        'recipient_count',
        'included_count',
        'status',
        'sealed_at',
        'rne_checked_at',
        'scheduled_at',
    ];

    protected function casts(): array
    {
        return [
            'manifest' => 'encrypted:array',
            'recipient_count' => 'integer',
            'included_count' => 'integer',
            'sealed_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('A sealed Twilio campaign manifest cannot be changed.');
        });

        static::deleting(function (): never {
            throw new LogicException('A sealed Twilio campaign cannot be deleted.');
        });
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(TwilioCampaignRecipient::class);
    }
}
