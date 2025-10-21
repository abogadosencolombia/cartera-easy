<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\TrustProxies;

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
        //
    })
    ->create();
