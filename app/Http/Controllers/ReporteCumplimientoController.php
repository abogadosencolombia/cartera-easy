<?php

    namespace App\Http\Controllers;

    use App\Models\ValidacionLegal;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\DB;
    use Inertia\Inertia;
    use Inertia\Response;

    class ReporteCumplimientoController extends Controller
    {
        public function __invoke(Request $request): Response
        {
            // Métrica 1: Conteo total de fallas activas.
            $totalFallasActivas = ValidacionLegal::where('estado', 'incumple')->count();

            // Métrica 2: Desglose de fallas por nivel de riesgo.
            $fallasPorRiesgo = ValidacionLegal::where('estado', 'incumple')
                ->select('nivel_riesgo', DB::raw('count(*) as total'))
                ->groupBy('nivel_riesgo')
                ->pluck('total', 'nivel_riesgo');

            // Métrica 3: Top 5 cooperativas con más fallas.
            $fallasPorCooperativa = ValidacionLegal::where('validaciones_legales.estado', 'incumple')
                ->join('casos', 'validaciones_legales.caso_id', '=', 'casos.id')
                ->join('cooperativas', 'casos.cooperativa_id', '=', 'cooperativas.id')
                ->select('cooperativas.nombre', DB::raw('count(*) as total_fallas'))
                ->groupBy('cooperativas.nombre')
                ->orderByDesc('total_fallas')
                ->limit(5)
                ->get();
                
            // Métrica 4: Lista detallada de todas las fallas activas para la tabla.
            $listadoFallas = ValidacionLegal::where('estado', 'incumple')
                ->with(['caso.deudor', 'caso.cooperativa', 'historial.usuario']) // Carga relaciones para mostrar info útil
                ->orderByRaw("FIELD(nivel_riesgo, 'alto', 'medio', 'bajo')") // Muestra las de riesgo alto primero
                ->latest('ultima_revision') // Las más recientes primero
                ->get();

            return Inertia::render('Reportes/Cumplimiento', [
                'stats' => [
                    'totalFallasActivas' => $totalFallasActivas,
                    'fallasPorRiesgo' => [
                        'alto' => $fallasPorRiesgo->get('alto', 0),
                        'medio' => $fallasPorRiesgo->get('medio', 0),
                        'bajo' => $fallasPorRiesgo->get('bajo', 0),
                    ],
                    'fallasPorCooperativa' => $fallasPorCooperativa,
                ],
                'listadoFallas' => $listadoFallas,
            ]);
        }
    }
    