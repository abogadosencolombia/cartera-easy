<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
// ELIMINAMOS LA LÍNEA DE RouteServiceProvider PORQUE NO LA NECESITAMOS
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use App\Events\UserLoggedIn;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
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
        $request->authenticate();

        $request->session()->regenerate();

        // Disparamos el evento de auditoría
        UserLoggedIn::dispatch($request->user(), $request);

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
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        // =================================================================
        // ===== INICIO: CORRECCIÓN ======================================
        // =================================================================
        // Cambiamos la redirección de vuelta a la raíz ('/') para que
        // muestre tu nueva página de inicio al cerrar sesión.
        return redirect('/');
        // =================================================================
        // ===== FIN DE LA CORRECCIÓN ====================================
        // =================================================================
    }
}

