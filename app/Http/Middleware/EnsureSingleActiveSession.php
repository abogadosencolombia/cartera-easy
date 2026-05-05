<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureSingleActiveSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if (! config('session.single_active', false)) {
            return $next($request);
        }

        if (! Schema::hasColumn($user->getTable(), 'active_session_id')) {
            return $next($request);
        }

        $currentSessionToken = $request->session()->get('active_session_token');

        if (! $currentSessionToken) {
            $currentSessionToken = Str::random(64);
            $request->session()->put('active_session_token', $currentSessionToken);
            $user->forceFill(['active_session_id' => $currentSessionToken])->save();

            return $next($request);
        }

        $activeSessionId = $user->active_session_id;

        if (! $activeSessionId) {
            $user->forceFill(['active_session_id' => $currentSessionToken])->save();

            return $next($request);
        }

        if (hash_equals((string) $activeSessionId, (string) $currentSessionToken)) {
            return $next($request);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->flash(
            'status',
            'Tu sesión se cerró porque esta cuenta inició sesión en otro equipo.'
        );

        if ($request->header('X-Inertia')) {
            return Inertia::location(route('login'));
        }

        return redirect()->route('login');
    }
}
