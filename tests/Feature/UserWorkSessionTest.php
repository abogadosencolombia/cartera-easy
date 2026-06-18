<?php

use App\Models\User;
use App\Models\UserWorkSession;
use Illuminate\Support\Carbon;

test('heartbeat stores elapsed session time as integer seconds', function () {
    $tz = config('app.timezone', 'America/Bogota');
    $user = User::factory()->create();
    $startedAt = Carbon::parse('2026-06-10 10:02:59', $tz);
    $heartbeatAt = Carbon::parse('2026-06-10 10:20:43.387239', $tz);

    $session = UserWorkSession::create([
        'user_id' => $user->id,
        'session_id_hash' => hash('sha256', 'test-session'),
        'started_at' => $startedAt,
        'last_activity_at' => $startedAt,
        'last_heartbeat_at' => $startedAt,
        'status' => 'activa',
    ]);

    Carbon::setTestNow($heartbeatAt);

    try {
        $response = $this
            ->withSession(['user_work_session_id' => $session->id])
            ->actingAs($user)
            ->post(route('jornadas.heartbeat'), [
                'active_seconds' => 180,
                'idle_seconds' => 0,
                'activity_detected' => true,
            ]);
    } finally {
        Carbon::setTestNow();
    }

    $response->assertOk()
        ->assertJsonPath('session.total_seconds', 1064)
        ->assertJsonPath('session.active_seconds', 180);

    $session->refresh();

    expect($session->total_seconds)->toBe(1064)
        ->and($session->active_seconds)->toBe(180)
        ->and($session->last_heartbeat_at->micro)->toBe(387239);
});
