<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
// ===== INICIO DE LA MODIFICACIÓN (PASO 2 - FIX CONTADOR) =====
// No necesitamos 'use Auth' porque ya estamos usando $request->user()
// No necesitamos 'use NotificacionCaso' porque usas la relación $user->notificaciones()
// ===== FIN DE LA MODIFICACIÓN =====

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
        
        // ===== INICIO DE LA MODIFICACIÓN (PASO 2 - VERSIÓN CORRECTA) =====

        // 1. Contador de notificaciones de CASOS (tu sistema original)
        $countCasos = $user
            ? $user->notificaciones()->where('leido', false)->where('fecha_envio', '<=', now())->count()
            : 0;

        // 2. Contador de notificaciones de TAREAS (nuevo sistema de Laravel)
        //    ->unreadNotifications() es el método de Laravel para la tabla 'notifications'
        $countTareas = $user
            ? $user->unreadNotifications()->count()
            : 0;

        // ===== FIN DE LA MODIFICACIÓN =====


        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? $user->only('id', 'name', 'email', 'tipo_usuario') : null,
                
                // ===== INICIO DE LA MODIFICACIÓN (PASO 2 - VERSIÓN CORRECTA) =====
                // 3. Sumamos los dos contadores
                'unreadNotifications' => $countCasos + $countTareas,
                // ===== FIN DE LA MODIFICACIÓN =====
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
        ]);
    }
}