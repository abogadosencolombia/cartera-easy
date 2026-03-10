<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current assets version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        
        // Inicializamos contadores en 0
        $countCasos = 0;
        $countTareas = 0;
        $countGestionDiaria = 0;

        if ($user) {
            // 1. Contador de notificaciones de CASOS
            if (method_exists($user, 'notificaciones')) {
                $countCasos = $user->notificaciones()
                    ->where('leido', false)
                    ->where('fecha_envio', '<=', now())
                    ->count();
            }

            // 2. Contador de notificaciones de TAREAS
            if (method_exists($user, 'unreadNotifications')) {
                $countTareas = $user->unreadNotifications()->count();
            }

            // 3. NUEVO: Contador de GESTIONES DIARIAS PENDIENTES
            $countGestionDiaria = \App\Models\NotaGestion::where('user_id', $user->id)
                ->where('is_completed', false)
                ->count();
        }

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? $user->only('id', 'name', 'email', 'tipo_usuario') : null,
                'unreadNotifications' => $countCasos + $countTareas,
                'pendingGestionDiaria' => $countGestionDiaria,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
        ]);
    }
}