<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserWorkSession;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class UserWorkSessionService
{
    private const SESSION_KEY = 'user_work_session_id';
    private const MAX_HEARTBEAT_DELTA_SECONDS = 900;
    private const IDLE_THRESHOLD_SECONDS = 300;

    public function start(User $user, Request $request): UserWorkSession
    {
        $now = now(config('app.timezone', 'America/Bogota'));

        $openWorkSession = UserWorkSession::query()
            ->where('user_id', $user->id)
            ->where('status', 'activa')
            ->latest('started_at')
            ->first();

        if ($openWorkSession) {
            return $this->attachToAuthenticatedSession($openWorkSession, $request, $now);
        }

        $workSession = UserWorkSession::create([
            'user_id' => $user->id,
            'session_id_hash' => $this->hashSessionId($request),
            'started_at' => $now,
            'last_activity_at' => $now,
            'last_heartbeat_at' => $now,
            'status' => 'activa',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'started_by' => 'login',
            ],
        ]);

        $request->session()->put(self::SESSION_KEY, $workSession->id);

        return $workSession;
    }

    public function heartbeat(Request $request, int $activeSeconds, int $idleSeconds, bool $activityDetected = false): ?UserWorkSession
    {
        $workSession = $this->current($request);

        if (!$workSession || $workSession->ended_at) {
            return null;
        }

        $activeSeconds = $this->sanitizeDelta($activeSeconds);
        $idleSeconds = $this->sanitizeDelta($idleSeconds);
        $now = now(config('app.timezone', 'America/Bogota'));
        $elapsedSeconds = $workSession->started_at
            ? $this->secondsBetween($workSession->started_at, $now)
            : 0;

        $workSession->forceFill([
            'active_seconds' => (int) $workSession->active_seconds + $activeSeconds,
            'idle_seconds' => (int) $workSession->idle_seconds + $idleSeconds,
            'last_activity_at' => $activityDetected ? $now : $workSession->last_activity_at,
            'last_heartbeat_at' => $now,
            'total_seconds' => max((int) $workSession->total_seconds, $elapsedSeconds),
        ])->save();

        return $workSession->refresh();
    }

    public function finishCurrent(Request $request, string $reason = 'logout'): ?array
    {
        $workSession = $this->current($request);

        if (!$workSession && $request->user()) {
            $workSession = UserWorkSession::query()
                ->where('user_id', $request->user()->id)
                ->where('status', 'activa')
                ->latest('started_at')
                ->first();
        }

        if (!$workSession) {
            return null;
        }

        $this->finish($workSession, now(config('app.timezone', 'America/Bogota')), $reason);
        $request->session()->forget(self::SESSION_KEY);

        return $this->summary($workSession->refresh());
    }

    public function summary(UserWorkSession $workSession): array
    {
        $totalSeconds = $workSession->effectiveTotalSeconds();
        $activeSeconds = max(0, (int) $workSession->active_seconds);
        $idleSeconds = max(0, (int) $workSession->idle_seconds);

        [$activeSeconds, $idleSeconds] = $this->includeUnmeasuredSeconds(
            $workSession,
            $totalSeconds,
            $activeSeconds,
            $idleSeconds,
            $workSession->effectiveEndedAt()
        );

        return [
            'id' => $workSession->id,
            'started_at' => optional($workSession->started_at)->toDateTimeString(),
            'ended_at' => optional($workSession->ended_at)->toDateTimeString(),
            'total_seconds' => $totalSeconds,
            'active_seconds' => $activeSeconds,
            'idle_seconds' => $idleSeconds,
            'total_human' => $this->humanDuration($totalSeconds),
            'active_human' => $this->humanDuration($activeSeconds),
            'idle_human' => $this->humanDuration($idleSeconds),
            'status' => $workSession->status,
            'logout_reason' => $workSession->logout_reason,
        ];
    }

    public function humanDuration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %02dm', $hours, $minutes);
        }

        if ($minutes > 0) {
            return sprintf('%dm %02ds', $minutes, $remainingSeconds);
        }

        return sprintf('%ds', $remainingSeconds);
    }

    public function finish(UserWorkSession $workSession, Carbon $endedAt, string $reason): void
    {
        if ($workSession->ended_at) {
            return;
        }

        $totalSeconds = $workSession->started_at
            ? $this->secondsBetween($workSession->started_at, $endedAt)
            : 0;

        $activeSeconds = max(0, (int) $workSession->active_seconds);
        $idleSeconds = max(0, (int) $workSession->idle_seconds);

        [$activeSeconds, $idleSeconds] = $this->includeUnmeasuredSeconds(
            $workSession,
            $totalSeconds,
            $activeSeconds,
            $idleSeconds,
            $endedAt
        );

        $workSession->forceFill([
            'ended_at' => $endedAt,
            'last_heartbeat_at' => $endedAt,
            'active_seconds' => $activeSeconds,
            'idle_seconds' => $idleSeconds,
            'total_seconds' => $totalSeconds,
            'status' => 'finalizada',
            'logout_reason' => $reason,
        ])->save();
    }

    private function current(Request $request): ?UserWorkSession
    {
        $id = $request->session()->get(self::SESSION_KEY);

        if (!$id) {
            return null;
        }

        return UserWorkSession::query()
            ->whereKey($id)
            ->where('user_id', optional($request->user())->id)
            ->first();
    }

    private function attachToAuthenticatedSession(
        UserWorkSession $workSession,
        Request $request,
        Carbon $now
    ): UserWorkSession {
        $totalSeconds = $workSession->started_at
            ? $this->secondsBetween($workSession->started_at, $now)
            : (int) $workSession->total_seconds;
        $activeSeconds = max(0, (int) $workSession->active_seconds);
        $idleSeconds = max(0, (int) $workSession->idle_seconds);

        [$activeSeconds, $idleSeconds] = $this->includeUnmeasuredSeconds(
            $workSession,
            $totalSeconds,
            $activeSeconds,
            $idleSeconds,
            $now
        );

        $workSession->forceFill([
            'session_id_hash' => $this->hashSessionId($request),
            'last_activity_at' => $now,
            'last_heartbeat_at' => $now,
            'active_seconds' => $activeSeconds,
            'idle_seconds' => $idleSeconds,
            'total_seconds' => $totalSeconds,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ])->save();

        $request->session()->put(self::SESSION_KEY, $workSession->id);

        return $workSession->refresh();
    }

    public function closeStaleOpenSessions(?Carbon $now = null, ?User $user = null): void
    {
        $now ??= now(config('app.timezone', 'America/Bogota'));
        $sessionLifetimeMinutes = (int) config('session.lifetime', 60);
        $cutoff = $now->copy()->subMinutes($sessionLifetimeMinutes);

        UserWorkSession::query()
            ->when($user, fn ($query) => $query->where('user_id', $user->id))
            ->where('status', 'activa')
            ->where(function ($query) use ($cutoff) {
                $query->where('last_heartbeat_at', '<=', $cutoff)
                    ->orWhere(function ($nested) use ($cutoff) {
                        $nested->whereNull('last_heartbeat_at')
                            ->where('started_at', '<=', $cutoff);
                    });
            })
            ->get()
            ->each(function (UserWorkSession $session) use ($now, $sessionLifetimeMinutes) {
                $reference = $session->last_heartbeat_at ?? $session->started_at ?? $now;
                $endedAt = $reference->copy()->addMinutes($sessionLifetimeMinutes);

                if ($endedAt->greaterThan($now)) {
                    $endedAt = $now;
                }

                $this->finish($session, $endedAt, 'sesion_obsoleta');
            });
    }

    private function includeUnmeasuredSeconds(
        UserWorkSession $workSession,
        int $totalSeconds,
        int $activeSeconds,
        int $idleSeconds,
        CarbonInterface $endedAt
    ): array {
        $totalSeconds = max(0, $totalSeconds);
        $activeSeconds = max(0, $activeSeconds);
        $idleSeconds = max(0, $idleSeconds);

        if ($activeSeconds + $idleSeconds > $totalSeconds) {
            $activeSeconds = min($activeSeconds, $totalSeconds);
            $idleSeconds = min($idleSeconds, max(0, $totalSeconds - $activeSeconds));

            return [$activeSeconds, $idleSeconds];
        }

        $missingSeconds = $totalSeconds - ($activeSeconds + $idleSeconds);

        if ($missingSeconds <= 0) {
            return [$activeSeconds, $idleSeconds];
        }

        [$extraActiveSeconds, $extraIdleSeconds] = $this->classifyUnmeasuredSeconds(
            $workSession,
            $endedAt,
            $missingSeconds
        );

        return [$activeSeconds + $extraActiveSeconds, $idleSeconds + $extraIdleSeconds];
    }

    private function classifyUnmeasuredSeconds(
        UserWorkSession $workSession,
        CarbonInterface $endedAt,
        int $missingSeconds
    ): array {
        $lastActivityAt = $workSession->last_activity_at ?? $workSession->started_at;

        if (!$lastActivityAt) {
            return [0, $missingSeconds];
        }

        $idleStartsAt = $lastActivityAt->copy()->addSeconds(self::IDLE_THRESHOLD_SECONDS);
        $extraIdleSeconds = $endedAt->greaterThan($idleStartsAt)
            ? min($missingSeconds, $this->secondsBetween($idleStartsAt, $endedAt))
            : 0;

        return [$missingSeconds - $extraIdleSeconds, $extraIdleSeconds];
    }

    private function sanitizeDelta(int $seconds): int
    {
        return max(0, min($seconds, self::MAX_HEARTBEAT_DELTA_SECONDS));
    }

    private function secondsBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        return max(0, (int) floor($from->diffInSeconds($to)));
    }

    private function hashSessionId(Request $request): ?string
    {
        $sessionId = $request->session()->getId();

        return $sessionId ? hash('sha256', $sessionId) : null;
    }
}
