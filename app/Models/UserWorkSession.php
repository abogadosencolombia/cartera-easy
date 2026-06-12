<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserWorkSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id_hash',
        'started_at',
        'last_activity_at',
        'last_heartbeat_at',
        'ended_at',
        'active_seconds',
        'idle_seconds',
        'total_seconds',
        'status',
        'logout_reason',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'ended_at' => 'datetime',
            'active_seconds' => 'integer',
            'idle_seconds' => 'integer',
            'total_seconds' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function effectiveEndedAt(): CarbonInterface
    {
        return $this->ended_at ?? now(config('app.timezone', 'America/Bogota'));
    }

    public function effectiveTotalSeconds(): int
    {
        if ($this->total_seconds > 0) {
            return $this->total_seconds;
        }

        if (!$this->started_at) {
            return 0;
        }

        return max(0, (int) floor($this->started_at->diffInSeconds($this->effectiveEndedAt())));
    }
}
