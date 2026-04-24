<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\TrustProxies;
// --- IMPORTACIONES AÑADIDAS PARA INERTIA ---
use Illuminate\Auth\AuthenticationException;
use Illuminate\Session\TokenMismatchException;
use Inertia\Inertia;
// ===== INICIO DE LA NUEVA CORRECCIÓN =====
// Importamos la excepción de Conflicto (Error 409)
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
// ===== FIN DE LA NUEVA CORRECCIÓN =====

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            TrustProxies::class,  // PRIMERO TrustProxies
            \App\Http\Middleware\HandleInertiaRequests::class,  // DESPUÉS Inertia
        ]);
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckUserRole::class,
            'juzgado.access' => \App\Http\Middleware\EnsureJuzgadoAccess::class,
        ]);
    })
    // IMPORTANTE: sin type-hint para evitar el TypeError entre versiones
    ->withSchedule(function ($schedule) {
            $schedule->job(new \App\Jobs\ProcesarAlertasProgramadas)
                ->everyMinute()
                ->name('procesar_alertas_programadas')
                ->withoutOverlapping(55)
                ->timezone(config('app.timezone'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        
        // --- MANEJADOR DE AUTORIZACIÓN (403) ---
        $exceptions->renderable(function (AccessDeniedHttpException $e, $request) {
            if ($request->header('X-Inertia')) {
                return back()->with('error', 'Acceso denegado: No tienes permiso para este recurso.');
            }
        });

        // --- MANEJADOR DE SESIÓN EXPIRADA (419) ---
        $exceptions->renderable(function (TokenMismatchException $e, $request) {
            if ($request->header('X-Inertia')) {
                return Inertia::location(route('login'));
            }
            return redirect()->guest(route('login'));
        });

        // --- MANEJADOR DE SESIÓN EXPIRADA (401) ---
        $exceptions->renderable(function (AuthenticationException $e, $request) {
            if ($request->header('X-Inertia')) {
                return Inertia::location(route('login'));
            }
            return redirect()->guest(route('login'));
        });

        // ===== INICIO DE LA NUEVA CORRECCIÓN (MANEJAR ERROR 409) =====
        /**
         * Esto maneja el error '409 Conflict' (Asset Mismatch).
         * Ocurre cuando corres 'npm run build' y el usuario tiene una
         * versión vieja de la app en su navegador.
         * Forzamos una recarga completa.
         */
        $exceptions->renderable(function (ConflictHttpException $e, $request) {
            if ($request->header('X-Inertia')) {
                // Le dice a Inertia que la URL ha cambiado a la URL actual,
                // forzando una recarga de página completa.
                return Inertia::location($request->url());
            }

            return response()->view('errors.409', [], 409); // Fallback
        });
        // ===== FIN DE LA NUEVA CORRECCIÓN =====

    })
    ->create();