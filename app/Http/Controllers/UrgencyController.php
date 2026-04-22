<?php

namespace App\Http\Controllers;

use App\Models\Caso;
use App\Models\ProcesoRadicado;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class UrgencyController extends Controller
{
    public function getList(Request $request)
    {
        $hoy = Carbon::today();
        $riesgoDate = $hoy->copy()->addDays(2);

        // 1. Obtener Radicados Urgentes
        $radicados = ProcesoRadicado::where('estado', 'ACTIVO')
            ->where('fecha_proxima_revision', '<=', $riesgoDate)
            ->with(['demandantes:id,nombre_completo', 'demandados:id,nombre_completo'])
            ->get()
            ->map(function($r) use ($hoy) {
                $dtes = $r->demandantes->pluck('nombre_completo')->take(2)->join(', ');
                $ddos = $r->demandados->pluck('nombre_completo')->take(2)->join(', ');
                
                return [
                    'id' => $r->id,
                    'tipo_modulo' => 'RADICADO',
                    'identificador' => $r->radicado ?? "ID #{$r->id}",
                    'partes' => "DTE: {$dtes} | DDO: {$ddos}",
                    'estado' => $r->fecha_proxima_revision < $hoy ? 'VENCIDO' : 'RIESGO',
                    'fecha_display' => $r->fecha_proxima_revision ? $r->fecha_proxima_revision->format('d/m/Y') : 'N/A',
                    'url' => route('procesos.show', $r->id)
                ];
            });

        // 2. Obtener Casos (Cooperativas) Urgentes (Inactivos > 10 días)
        $casos = Caso::where('estado_proceso', '!=', 'cerrado')
            ->where('updated_at', '<', now()->subDays(10))
            ->with(['deudor:id,nombre_completo'])
            ->get()
            ->map(function($c) {
                return [
                    'id' => $c->id,
                    'tipo_modulo' => 'CASO',
                    'identificador' => $c->radicado ?? "CASO #{$c->id}",
                    'partes' => $c->deudor?->nombre_completo ?? 'Sin deudor',
                    'estado' => 'VENCIDO', // Inactividad larga se considera vencido
                    'fecha_display' => $c->updated_at->format('d/m/Y'),
                    'url' => route('casos.show', $c->id)
                ];
            });

        // 3. Unificar y Paginar manualmente
        $all = $radicados->concat($casos)->sortByDesc(function($item) {
            return $item['estado'] === 'VENCIDO' ? 2 : 1;
        });

        $page = $request->input('page', 1);
        $perPage = 5;
        $items = $all->forPage($page, $perPage)->values();
        
        return new LengthAwarePaginator(
            $items,
            $all->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }
}
