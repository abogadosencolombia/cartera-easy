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
        $urgencias = [
            'total' => 0,
            'casos_vencidos' => 0,
            'casos_riesgo' => 0,
            'radicados_vencidos' => 0,
            'radicados_riesgo' => 0,
            'lista' => [] // Top 5 más urgentes
        ];

        if ($user) {
            // 1. Notificaciones estándar...
            if (method_exists($user, 'notificaciones')) {
                $countCasos = $user->notificaciones()->where('leido', false)->where('fecha_envio', '<=', now())->count();
            }
            if (method_exists($user, 'unreadNotifications')) {
                $countTareas = $user->unreadNotifications()->count();
            }
            $countGestionDiaria = \App\Models\NotaGestion::where('user_id', $user->id)->where('is_completed', false)->count();

            // 2. CÁLCULO DE URGENCIAS GLOBAL (PARA TODO EL EQUIPO)
            if (in_array($user->tipo_usuario, ['admin', 'abogado', 'gestor'])) {
                $hoy = \Carbon\Carbon::today();
                $riesgoDate = $hoy->copy()->addDays(2);

                // --- RADICADOS (ABOGADOS COLOMBIA) - CONTEO GLOBAL ---
                $queryRad = \App\Models\ProcesoRadicado::where('estado', 'ACTIVO');
                
                $urgencias['radicados_vencidos'] = (clone $queryRad)->where('fecha_proxima_revision', '<', $hoy)->count();
                $urgencias['radicados_riesgo'] = (clone $queryRad)->whereBetween('fecha_proxima_revision', [$hoy, $riesgoDate])->count();

                // --- CASOS (COOPERATIVAS) - CONTEO GLOBAL ---
                $queryCasos = \App\Models\Caso::where('estado_proceso', '!=', 'cerrado');

                $urgencias['casos_vencidos'] = (clone $queryCasos)->where('updated_at', '<', now()->subDays(10))->count();

                $urgencias['total'] = $urgencias['radicados_vencidos'] + $urgencias['radicados_riesgo'] + $urgencias['casos_vencidos'];
            }
        }

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? $user->only('id', 'name', 'email', 'tipo_usuario', 'tour_seen') : null,
                'unreadNotifications' => $countCasos + $countTareas,
                'pendingGestionDiaria' => $countGestionDiaria,
                'urgencias' => $urgencias,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
        ]);
    }
}