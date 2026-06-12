<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserWorkSession;
use App\Services\UserWorkSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserWorkSessionController extends Controller
{
    public function heartbeat(Request $request, UserWorkSessionService $service): JsonResponse
    {
        $validated = $request->validate([
            'active_seconds' => ['nullable', 'integer', 'min:0', 'max:900'],
            'idle_seconds' => ['nullable', 'integer', 'min:0', 'max:900'],
            'activity_detected' => ['nullable', 'boolean'],
        ]);

        $session = $service->heartbeat(
            $request,
            (int) ($validated['active_seconds'] ?? 0),
            (int) ($validated['idle_seconds'] ?? 0),
            $request->boolean('activity_detected')
        );

        return response()->json([
            'ok' => true,
            'session' => $session ? $service->summary($session) : null,
        ]);
    }

    public function index(Request $request, UserWorkSessionService $service): Response
    {
        $filters = $request->validate([
            'periodo' => ['nullable', 'in:hoy,semana,mes,personalizado'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date'],
        ]);

        [$from, $to] = $this->resolveDateRange($filters);

        $baseQuery = UserWorkSession::query()
            ->with('user:id,name,email,tipo_usuario,deleted_at')
            ->where('started_at', '<=', $to)
            ->where(function ($query) use ($from) {
                $query->whereNull('ended_at')
                    ->orWhere('ended_at', '>=', $from);
            })
            ->when(!empty($filters['user_id']), fn ($query) => $query->where('user_id', $filters['user_id']));

        $allForSummary = (clone $baseQuery)->get();
        $summary = $this->buildSummary($allForSummary, $service);

        $sessions = $baseQuery
            ->latest('started_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (UserWorkSession $session) => $this->serializeSession($session, $service));

        return Inertia::render('Admin/Jornadas/Index', [
            'sessions' => $sessions,
            'users' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'tipo_usuario']),
            'filters' => [
                'periodo' => $filters['periodo'] ?? 'hoy',
                'user_id' => $filters['user_id'] ?? '',
                'fecha_desde' => $filters['fecha_desde'] ?? $from->toDateString(),
                'fecha_hasta' => $filters['fecha_hasta'] ?? $to->toDateString(),
            ],
            'summary' => $summary,
        ]);
    }

    private function resolveDateRange(array $filters): array
    {
        $tz = config('app.timezone', 'America/Bogota');
        $period = $filters['periodo'] ?? 'hoy';
        $today = now($tz);

        return match ($period) {
            'semana' => [$today->copy()->startOfWeek()->startOfDay(), $today->copy()->endOfWeek()->endOfDay()],
            'mes' => [$today->copy()->startOfMonth()->startOfDay(), $today->copy()->endOfMonth()->endOfDay()],
            'personalizado' => [
                !empty($filters['fecha_desde']) ? \Carbon\Carbon::parse($filters['fecha_desde'], $tz)->startOfDay() : $today->copy()->startOfDay(),
                !empty($filters['fecha_hasta']) ? \Carbon\Carbon::parse($filters['fecha_hasta'], $tz)->endOfDay() : $today->copy()->endOfDay(),
            ],
            default => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
        };
    }

    private function buildSummary($sessions, UserWorkSessionService $service): array
    {
        $totalSeconds = 0;
        $activeSeconds = 0;
        $idleSeconds = 0;
        $activeSessions = 0;

        foreach ($sessions as $session) {
            $sessionSummary = $service->summary($session);
            $totalSeconds += $sessionSummary['total_seconds'];
            $activeSeconds += $sessionSummary['active_seconds'];
            $idleSeconds += $sessionSummary['idle_seconds'];
            $activeSessions += $session->status === 'activa' ? 1 : 0;
        }

        $sessionCount = $sessions->count();

        return [
            'sessions_count' => $sessionCount,
            'users_count' => $sessions->pluck('user_id')->unique()->count(),
            'active_sessions_count' => $activeSessions,
            'total_seconds' => $totalSeconds,
            'active_seconds' => $activeSeconds,
            'idle_seconds' => $idleSeconds,
            'total_human' => $service->humanDuration($totalSeconds),
            'active_human' => $service->humanDuration($activeSeconds),
            'idle_human' => $service->humanDuration($idleSeconds),
            'idle_percentage' => $totalSeconds > 0 ? round(($idleSeconds / $totalSeconds) * 100, 1) : 0,
        ];
    }

    private function serializeSession(UserWorkSession $session, UserWorkSessionService $service): array
    {
        $summary = $service->summary($session);

        return [
            'id' => $session->id,
            'user' => $session->user ? [
                'id' => $session->user->id,
                'name' => $session->user->name,
                'email' => $session->user->email,
                'tipo_usuario' => $session->user->tipo_usuario,
            ] : null,
            'started_at' => optional($session->started_at)->toDateTimeString(),
            'ended_at' => optional($session->ended_at)->toDateTimeString(),
            'last_activity_at' => optional($session->last_activity_at)->toDateTimeString(),
            'last_heartbeat_at' => optional($session->last_heartbeat_at)->toDateTimeString(),
            'status' => $session->status,
            'logout_reason' => $session->logout_reason,
            'ip_address' => $session->ip_address,
            ...$summary,
        ];
    }
}
