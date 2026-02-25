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

        if ($user) {
            // 1. Contador de notificaciones de CASOS (Tu sistema personalizado)
            // Verificamos que la relación exista para evitar errores
            if (method_exists($user, 'notificaciones')) {
                $countCasos = $user->notificaciones()
                    ->where('leido', false)
                    ->where('fecha_envio', '<=', now())
                    ->count();
            }

            // 2. Contador de notificaciones de TAREAS (Sistema nativo Laravel)
            // Verificamos que el trait Notifiable esté activo y funcionando
            if (method_exists($user, 'unreadNotifications')) {
                $countTareas = $user->unreadNotifications()->count();
            }
        }

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? $user->only('id', 'name', 'email', 'tipo_usuario') : null,
                
                // 3. Sumamos los dos contadores para la campanita del Layout
                'unreadNotifications' => $countCasos + $countTareas,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
        ]);
    }
}