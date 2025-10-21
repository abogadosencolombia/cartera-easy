<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AlertasController extends Controller
{
    // ================= Listados JSON =================

    public function preventivas(Request $request): JsonResponse
    {
        $days = (int) $request->integer('days', 7);
        return response()->json([
            'ok'    => true,
            'days'  => $days,
            'items' => $this->fakeItems($days, 'preventivas'),
        ]);
    }

    public function reactivas(Request $request): JsonResponse
    {
        $days = (int) $request->integer('days', 7);
        return response()->json([
            'ok'    => true,
            'days'  => $days,
            'items' => $this->fakeItems($days, 'reactivas'),
        ]);
    }

    public function hallazgos(Request $request): JsonResponse
    {
        $days = (int) $request->integer('days', 30);
        return response()->json([
            'ok'    => true,
            'days'  => $days,
            'items' => $this->fakeItems($days, 'hallazgos'),
        ]);
    }

    public function reporteCliente(Request $request): JsonResponse
    {
        $days = (int) $request->integer('days', 15);
        return response()->json([
            'ok'    => true,
            'days'  => $days,
            'items' => $this->fakeItems($days, 'reporte cliente'),
        ]);
    }

    public function vigilanciaJudicial(Request $request): JsonResponse
    {
        $days = (int) $request->integer('days', 8);
        return response()->json([
            'ok'    => true,
            'days'  => $days,
            'items' => $this->fakeItems($days, 'vigilancia judicial'),
        ]);
    }

    // ================= Exportaciones CSV =================

    public function exportPreventivas(Request $request): StreamedResponse
    {
        $days = (int) $request->integer('days', 7);
        return $this->csvDownload('alertas_preventivas', $this->fakeItems($days, 'preventivas'));
    }

    public function exportReactivas(Request $request): StreamedResponse
    {
        $days = (int) $request->integer('days', 7);
        return $this->csvDownload('alertas_reactivas', $this->fakeItems($days, 'reactivas'));
    }

    public function exportHallazgos(Request $request): StreamedResponse
    {
        $days = (int) $request->integer('days', 30);
        return $this->csvDownload('alertas_hallazgos', $this->fakeItems($days, 'hallazgos'));
    }

    public function exportReporteCliente(Request $request): StreamedResponse
    {
        $days = (int) $request->integer('days', 15);
        return $this->csvDownload('alertas_reporte_cliente', $this->fakeItems($days, 'reporte cliente'));
    }

    public function exportVigilanciaJudicial(Request $request): StreamedResponse
    {
        $days = (int) $request->integer('days', 8);
        return $this->csvDownload('alertas_vigilancia_judicial', $this->fakeItems($days, 'vigilancia judicial'));
    }

    // ================= Helpers =================

    /**
     * Reemplaza por tu consulta real. Esto es demo.
     */
    private function fakeItems(int $days, string $tipo): array
    {
        $today = Carbon::today()->toDateString();

        return [
            [
                'id'                => 1,
                'rad_corto'         => '11001-31-03-001-2025-00001-00',
                'etapa'             => ucfirst($tipo),
                'ultima_actuacion'  => $today,
                'vencimiento'       => Carbon::today()->addDays($days)->toDateString(),
                'dias_sin_gestion'  => min($days, 5),
                'encargado'         => 'Equipo Jurídico',
            ],
        ];
    }

    private function csvDownload(string $baseName, array $rows): StreamedResponse
    {
        $name = $baseName.'_'.now()->format('Ymd_His').'.csv';
        $headers = ['Content-Type' => 'text/csv; charset=UTF-8'];

        $columns = ['ID', 'Rad. Corto', 'Etapa', 'Últ. Actuación', 'Vencimiento', 'Días sin gestión', 'Encargado'];

        return response()->streamDownload(function () use ($columns, $rows) {
            $out = fopen('php://output', 'w');
            // BOM para Excel en Windows
            fwrite($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, $columns, ';');

            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['id'] ?? '',
                    $r['rad_corto'] ?? '',
                    $r['etapa'] ?? '',
                    $r['ultima_actuacion'] ?? '',
                    $r['vencimiento'] ?? '',
                    $r['dias_sin_gestion'] ?? '',
                    $r['encargado'] ?? '',
                ], ';');
            }
            fclose($out);
        }, $name, $headers);
    }
}
