<?php

use App\Models\Caso;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$rows = json_decode(file_get_contents('/tmp/angie_portfolio_rows.json'), true, 512, JSON_THROW_ON_ERROR);
$angieId = 46;
$stats = ['updated' => 0, 'created' => 0, 'assignment_changes' => 0, 'case_ids' => []];

$normalizeDocument = static fn ($value) => ltrim(preg_replace('/\D/', '', (string) $value), '0') ?: '0';
$isRadicado = static fn ($value) => preg_match('/^\d{20,24}$/', preg_replace('/\D/', '', (string) $value)) === 1;

DB::transaction(function () use ($rows, $angieId, &$stats, $normalizeDocument, $isRadicado) {
    foreach ($rows as $row) {
        $document = $normalizeDocument($row['document']);
        $obligation = trim((string) $row['obligation']);

        $candidates = Caso::withTrashed()
            ->whereHas('deudor', function ($query) use ($document, $normalizeDocument) {
                $query->whereRaw("TRIM(LEADING '0' FROM REGEXP_REPLACE(numero_documento, '[^0-9]', '')) = ?", [$document]);
            })
            ->get();

        $case = $candidates->first(fn ($item) => trim((string) $item->referencia_credito) === $obligation);
        if (!$case) {
            $origin = $candidates->firstOrFail();
            $case = $origin->replicate([
                'referencia_credito', 'radicado', 'etapa_actual', 'etapa_procesal',
                'ultima_actividad', 'notas_legales', 'link_drive', 'link_expediente',
                'is_pinned', 'deleted_at',
            ]);
            $case->referencia_credito = $obligation;
            $case->user_id = $angieId;
            $case->save();
            $stats['created']++;
        }

        $wasAssignedOnlyToAngie = (int) $case->user_id === $angieId
            && $case->users()->pluck('users.id')->map(fn ($id) => (int) $id)->all() === [$angieId];

        $case->user_id = $angieId;
        $case->referencia_credito = $obligation;
        $case->monto_total = $row['total_capital'];
        $case->monto_deuda_actual = $row['total_capital'];

        $sourceValue = trim((string) ($row['radicado_or_status'] ?? ''));
        if ($isRadicado($sourceValue)) {
            $case->radicado = preg_replace('/\D/', '', $sourceValue);
            $case->etapa_actual = 'DEMANDA PRESENTADA';
            $case->etapa_procesal = 'DEMANDA PRESENTADA';
        } elseif ($sourceValue !== '') {
            $stage = match (Str::upper($sourceValue)) {
                'PRESENTADA' => 'DEMANDA PRESENTADA',
                'LISTA PARA PRESENTAR' => 'PENDIENTE PRESENTACIÓN DE DEMANDA',
                default => Str::upper($sourceValue),
            };
            $case->etapa_actual = $stage;
            $case->etapa_procesal = $stage;
            if (Str::upper($sourceValue) === 'CANCELADO') {
                $case->estado_proceso = 'cerrado';
            }
        }

        $marker = "Actualizado desde CARTERA TOTAL  SANDRA DUQUE ORIGINAL (1) (4) (3).xlsx — {$row['sheet']}, fila {$row['row']}";
        if (!str_contains((string) $case->notas_legales, $marker)) {
            $details = [
                $marker,
                'Responsable asignada: ANGGIE COUTIN',
                'Obligación: '.$obligation,
                'Capital total: '.$row['total_capital'],
                'Capital al 60 (venta): '.$row['sale_capital'],
                'Radicado/estado reportado: '.($sourceValue ?: 'Sin información'),
            ];
            $case->notas_legales = trim((string) $case->notas_legales)."\n\n".implode("\n", $details);
        }

        $case->save();
        $case->users()->sync([$angieId]);

        if (!$wasAssignedOnlyToAngie) {
            $stats['assignment_changes']++;
        }
        $stats['updated']++;
        $stats['case_ids'][] = $case->id;
    }
});

$stats['case_ids'] = array_values(array_unique($stats['case_ids']));
return $stats;
