<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
// ELIMINAMOS LA LÍNEA DE RouteServiceProvider PORQUE NO LA NECESITAMOS
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use App\Events\UserLoggedIn;
use App\Services\UserWorkSessionService;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request): Response
    {
        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $request->session()->flash(
                'status',
                'Se cerró una sesión previa en este navegador. Ingresa con tu usuario.'
            );
        }

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();
        $sessionId = $request->session()->getId();
        $sessionToken = Str::random(64);
        $loginAttributes = [
            'remember_token' => Str::random(60),
        ];

        if ($this->shouldEnforceSingleActiveSession($user)) {
            $request->session()->put('active_session_token', $sessionToken);
            $loginAttributes['active_session_id'] = $sessionToken;
        }

        $user->forceFill($loginAttributes)->save();

        app(UserWorkSessionService::class)->start($user, $request);

        if (config('session.single_active', false)) {
            $this->deleteOtherDatabaseSessions((int) $user->id, $sessionId);
        }

        // Disparamos el evento de auditoría
        UserLoggedIn::dispatch($user, $request);

        // =================================================================
        // ===== CAMBIO CLAVE: Redirigimos directamente al dashboard =====
        // =================================================================
        return redirect()->intended('/dashboard');
        // =================================================================
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        $shouldFinishTurn = $request->input('reason') === 'finish_turn';
        $turnoResumen = $shouldFinishTurn
            ? app(UserWorkSessionService::class)->finishCurrent($request, 'turno_finalizado')
            : null;
        $sessionToken = $request->session()->get('active_session_token');

        if ($user) {
            $logoutAttributes = [
                'remember_token' => Str::random(60),
            ];

            if (
                $this->shouldEnforceSingleActiveSession($user)
                && $sessionToken
                && hash_equals((string) $user->active_session_id, (string) $sessionToken)
            ) {
                $logoutAttributes['active_session_id'] = null;
            }

            $user->forceFill($logoutAttributes)->save();
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        $redirect = redirect('/');

        if ($turnoResumen) {
            $redirect->with('workSessionSummary', $turnoResumen);
        }

        return $redirect;
    }

    private function deleteOtherDatabaseSessions(int $userId, string $currentSessionId): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $userId)
            ->where('id', '!=', $currentSessionId)
            ->delete();
    }

    private function usersHaveActiveSessionColumn($user): bool
    {
        return Schema::hasColumn($user->getTable(), 'active_session_id');
    }

    private function shouldEnforceSingleActiveSession($user): bool
    {
        return config('session.single_active', false)
            && $this->usersHaveActiveSessionColumn($user);
    }
}
