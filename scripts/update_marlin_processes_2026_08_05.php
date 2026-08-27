<?php

use App\Models\Caso;
use App\Models\Juzgado;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$source = '/tmp/marlin_process_updates.json';
$rows = json_decode(file_get_contents($source), true, 512, JSON_THROW_ON_ERROR);
$marlinId = 47;
$stats = [
    'updated' => 0,
    'created' => 0,
    'assignment_changes' => 0,
    'data_changes' => 0,
    'judge_matches' => 0,
    'judge_missing' => [],
    'case_ids' => [],
];

$normalize = static fn ($value) => preg_replace(
    '/[^A-Z0-9]/',
    '',
    Str::upper(Str::ascii(preg_replace('/^\s*\([^)]*\)\s*/', '', (string) $value)))
);

$judges = Juzgado::query()->get(['id', 'nombre'])->map(fn ($judge) => [
    'id' => $judge->id,
    'name' => $normalize($judge->nombre),
]);

DB::transaction(function () use ($rows, $marlinId, &$stats, $normalize, $judges) {
    foreach ($rows as $row) {
        if (!empty($row['clone_from_id'])) {
            $origin = Caso::withTrashed()->findOrFail($row['clone_from_id']);
            $case = $origin->replicate([
                'radicado', 'etapa_actual', 'etapa_procesal', 'ultima_actividad',
                'notas_legales', 'is_pinned', 'deleted_at',
            ]);
            $case->user_id = $marlinId;
            $case->referencia_credito = (string) $row['id_proceso'];
            $case->save();
            $stats['created']++;
        } else {
            $case = Caso::withTrashed()->findOrFail($row['target_id']);
        }

        $before = $case->only([
            'user_id', 'radicado', 'etapa_actual', 'etapa_procesal',
            'ultima_actividad', 'subtipo_proceso', 'juzgado_id',
        ]);

        $case->user_id = $marlinId;
        if (!empty($row['radicado'])) {
            $case->radicado = (string) $row['radicado'];
        }
        if (!empty($row['etapa_procesal'])) {
            $case->etapa_actual = trim((string) $row['etapa_procesal']);
            $case->etapa_procesal = trim((string) $row['etapa_procesal']);
        }
        if (!empty($row['fecha_ult_actuacion'])) {
            $case->ultima_actividad = $row['fecha_ult_actuacion'];
        } elseif (!empty($row['fecha_etapa'])) {
            $case->ultima_actividad = $row['fecha_etapa'];
        }
        if (!empty($row['tipo_proceso']) && Str::upper((string) $row['tipo_proceso']) !== 'NA') {
            $case->subtipo_proceso = trim((string) $row['tipo_proceso']);
        }

        $judgeName = trim((string) ($row['nombre_juzgado_actual'] ?: $row['nombre_juzgado_original']));
        if ($judgeName !== '') {
            $wanted = $normalize($judgeName);
            $judge = $judges->first(fn ($item) => $item['name'] === $wanted)
                ?? $judges->first(fn ($item) => str_contains($item['name'], $wanted) || str_contains($wanted, $item['name']));
            if ($judge) {
                $case->juzgado_id = $judge['id'];
                $stats['judge_matches']++;
            } else {
                $stats['judge_missing'][$judgeName] = true;
            }
        }

        $sourceIds = implode(', ', $row['source_ids'] ?? [(string) $row['id_proceso']]);
        $marker = "Actualizado desde mis_procesos-2026-08-05.xlsx — ID(s) proceso: {$sourceIds}";
        if (!str_contains((string) $case->notas_legales, $marker)) {
            $details = [
                $marker,
                'Etapa: '.($row['etapa_procesal'] ?: 'Sin información'),
                'Fecha etapa: '.($row['fecha_etapa'] ?: 'Sin información'),
                'Última actuación: '.($row['fecha_ult_actuacion'] ?: 'Sin información'),
                'Gestión oportuna: '.($row['gestion_oportuna'] ?: 'Sin información'),
            ];
            if (!empty($row['observacion'])) {
                $details[] = 'Observación: '.trim((string) $row['observacion']);
            }
            $case->notas_legales = trim((string) $case->notas_legales)."\n\n".implode("\n", $details);
        }

        $case->save();
        $case->users()->sync([$marlinId]);

        $after = $case->fresh()->only(array_keys($before));
        if ($before['user_id'] !== $marlinId) {
            $stats['assignment_changes']++;
        }
        if ($before !== $after) {
            $stats['data_changes']++;
        }
        $stats['updated']++;
        $stats['case_ids'][] = $case->id;
    }
});

$stats['judge_missing'] = array_keys($stats['judge_missing']);
$stats['case_ids'] = array_values(array_unique($stats['case_ids']));

return $stats;
