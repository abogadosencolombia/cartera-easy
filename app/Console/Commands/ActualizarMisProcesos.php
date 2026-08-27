<?php

namespace App\Console\Commands;

use App\Models\AuditoriaEvento;
use App\Models\Caso;
use App\Models\Juzgado;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

class ActualizarMisProcesos extends Command
{
    protected $signature = 'casos:actualizar-mis-procesos
        {archivo : Ruta del archivo mis_procesos en formato XLSX/XLS}
        {--apply : Aplicar los cambios; sin esta opción solo se simulan}
        {--json : Emitir el resultado como JSON para auditoría automatizada}';

    protected $description = 'Sincroniza de forma segura los campos operativos de mis_procesos con Casos';

    private const REQUIRED_HEADERS = [
        'id_proceso',
        'id_deudor',
        'nombre_deudor',
        'radicado',
        'etapa_procesal',
        'fecha_etapa',
        'nombre_juzgado_original',
        'nombre_juzgado_actual',
        'fecha_ult_actuacion',
        'gestion_oportuna',
    ];

    private const MUTABLE_FIELDS = [
        'radicado',
        'etapa_actual',
        'etapa_procesal',
        'ultima_actividad',
        'juzgado_id',
    ];

    /**
     * Excepción verificada contra el manifiesto aplicado el 5 de agosto.
     * Este proceso no conservaba marcador de origen ni el radicado nuevo.
     */
    private const VERIFIED_CASE_OVERRIDES = [
        '164630' => 171,
    ];

    public function handle(): int
    {
        try {
            $path = $this->resolvePath((string) $this->argument('archivo'));
            $rows = $this->readRows($path);
            $missingStages = $rows->pluck('etapa_procesal')
                ->filter()
                ->unique()
                ->diff(DB::table('etapas_procesales')->pluck('nombre'))
                ->values();
            if ($missingStages->isNotEmpty()) {
                throw new RuntimeException(
                    'El catálogo no contiene estas etapas del archivo: '.$missingStages->implode(', '),
                );
            }

            $groups = $this->consolidateRows($rows);
            $duplicates = $groups
                ->filter(fn (array $group) => count($group['source_ids']) > 1)
                ->map(fn (array $group) => [
                    'radicado' => $group['winner']['radicado'],
                    'source_ids' => $group['source_ids'],
                ])
                ->values();

            $cases = Caso::withTrashed()
                ->with(['deudor:id,nombre_completo,numero_documento', 'juzgado:id,nombre'])
                ->get();

            $judges = Juzgado::query()->get(['id', 'nombre']);
            $judgeIndex = $this->buildJudgeIndex($judges);
            $mapping = $this->mapGroupsToCases($groups, $cases);

            if ($mapping['errors']->isNotEmpty()) {
                return $this->failWithMappingErrors($path, $rows, $groups, $mapping, $duplicates);
            }

            $planned = collect();
            $unresolvedJudges = collect();
            $planningErrors = collect();

            foreach ($mapping['matches'] as $match) {
                $case = $match['case'];
                $group = $match['group'];
                $judgeResult = $this->resolveJudge($case, $group['winner'], $judgeIndex);
                $changes = $this->calculateChanges($case, $group['winner'], $judgeResult['id']);

                $sourceDate = $group['winner']['fecha_ult_actuacion'] ?: $group['winner']['fecha_etapa'];
                $currentDate = $this->modelDate($case->ultima_actividad);
                if ($changes !== [] && $sourceDate && $currentDate && $sourceDate < $currentDate) {
                    $planningErrors->push(
                        "Caso #{$case->id}: la fecha fuente {$sourceDate} es anterior a la última actividad {$currentDate}",
                    );
                    continue;
                }

                if (isset($changes['radicado'])) {
                    $conflict = $cases->first(fn (Caso $candidate) => $candidate->id !== $case->id
                        && $this->radicado($candidate->radicado) === $changes['radicado']);
                    if ($conflict) {
                        $planningErrors->push(
                            "Caso #{$case->id}: el radicado {$changes['radicado']} ya pertenece al Caso #{$conflict->id}",
                        );
                        continue;
                    }
                }

                if ($judgeResult['unresolved']) {
                    $unresolvedJudges->push([
                        'caso_id' => $case->id,
                        'source_ids' => $group['source_ids'],
                        'juzgado' => $judgeResult['wanted_name'],
                        'radicado' => $group['winner']['radicado'],
                    ]);
                }

                if ($changes !== []) {
                    $planned->push([
                        'case' => $case,
                        'group' => $group,
                        'score' => $match['score'],
                        'changes' => $changes,
                    ]);
                }
            }

            if ($planningErrors->isNotEmpty()) {
                throw new RuntimeException(
                    'La sincronización fue bloqueada por controles de integridad: '.$planningErrors->implode('; '),
                );
            }

            $fieldCounts = collect(self::MUTABLE_FIELDS)
                ->mapWithKeys(fn (string $field) => [
                    $field => $planned->filter(fn (array $item) => array_key_exists($field, $item['changes']))->count(),
                ])
                ->filter()
                ->all();

            $report = [
                'archivo' => basename($path),
                'modo' => $this->option('apply') ? 'aplicar' : 'simulacion',
                'filas' => $rows->count(),
                'ids_proceso_unicos' => $rows->pluck('id_proceso')->unique()->count(),
                'casos_logicos' => $groups->count(),
                'radicados_duplicados_consolidados' => $duplicates->all(),
                'casos_mapeados' => $mapping['matches']->count(),
                'casos_faltantes' => 0,
                'casos_por_actualizar' => $planned->count(),
                'casos_al_dia' => $groups->count() - $planned->count(),
                'cambios_por_campo' => $fieldCounts,
                'juzgados_no_resueltos' => $unresolvedJudges->all(),
                'cambios' => $this->serializeChanges($planned),
            ];

            if ($this->option('apply') && $planned->isNotEmpty()) {
                $this->applyChanges($planned, basename($path));
                $report['aplicados'] = $planned->count();
            } else {
                $report['aplicados'] = 0;
            }

            $this->renderReport($report);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            if ($this->option('json')) {
                $this->line(json_encode([
                    'ok' => false,
                    'error' => $exception->getMessage(),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            } else {
                $this->error($exception->getMessage());
            }

            return self::FAILURE;
        }
    }

    private function resolvePath(string $argument): string
    {
        $candidate = str_starts_with($argument, DIRECTORY_SEPARATOR)
            ? $argument
            : base_path($argument);
        $path = realpath($candidate);

        if ($path === false || ! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("No se encontró un archivo legible en: {$candidate}");
        }

        if (! in_array(Str::lower(pathinfo($path, PATHINFO_EXTENSION)), ['xlsx', 'xls'], true)) {
            throw new RuntimeException('El archivo debe tener extensión XLSX o XLS.');
        }

        return $path;
    }

    private function readRows(string $path): Collection
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();
        $matrix = $sheet->rangeToArray(
            "A1:{$highestColumn}{$highestRow}",
            null,
            true,
            true,
            false,
        );
        $spreadsheet->disconnectWorksheets();

        if (count($matrix) < 2) {
            throw new RuntimeException('El libro no contiene filas de procesos.');
        }

        $headers = array_map(fn ($value) => $this->normalizeHeader($value), array_shift($matrix));
        $missingHeaders = array_values(array_diff(self::REQUIRED_HEADERS, $headers));

        if ($missingHeaders !== []) {
            throw new RuntimeException('Faltan encabezados requeridos: '.implode(', ', $missingHeaders));
        }

        $rows = collect();
        foreach ($matrix as $offset => $values) {
            $rowNumber = $offset + 2;
            $values = array_pad($values, count($headers), null);
            $row = array_combine($headers, array_slice($values, 0, count($headers)));
            $id = $this->identifier($row['id_proceso'] ?? null);

            if ($id === '') {
                if (collect($values)->filter(fn ($value) => $value !== null && trim((string) $value) !== '')->isEmpty()) {
                    continue;
                }

                throw new RuntimeException("La fila {$rowNumber} no tiene id_proceso.");
            }

            $rows->push([
                ...$row,
                '_row' => $rowNumber,
                'id_proceso' => $id,
                'id_deudor' => $this->identifier($row['id_deudor'] ?? null),
                'radicado' => $this->radicado($row['radicado'] ?? null),
                'nombre_deudor' => trim((string) ($row['nombre_deudor'] ?? '')),
                'etapa_procesal' => trim((string) ($row['etapa_procesal'] ?? '')),
                'fecha_etapa' => $this->date($row['fecha_etapa'] ?? null, $rowNumber, 'fecha_etapa'),
                'fecha_ult_actuacion' => $this->date($row['fecha_ult_actuacion'] ?? null, $rowNumber, 'fecha_ult_actuacion'),
                'nombre_juzgado_original' => trim((string) ($row['nombre_juzgado_original'] ?? '')),
                'nombre_juzgado_actual' => trim((string) ($row['nombre_juzgado_actual'] ?? '')),
                'gestion_oportuna' => trim((string) ($row['gestion_oportuna'] ?? '')),
            ]);
        }

        $duplicateIds = $rows->groupBy('id_proceso')->filter(fn (Collection $items) => $items->count() > 1)->keys();
        if ($duplicateIds->isNotEmpty()) {
            throw new RuntimeException('Hay id_proceso repetidos: '.$duplicateIds->implode(', '));
        }

        return $rows;
    }

    private function consolidateRows(Collection $rows): Collection
    {
        return $rows
            ->groupBy(fn (array $row) => strlen($row['radicado']) === 23
                ? 'radicado:'.$row['radicado']
                : 'id:'.$row['id_proceso'])
            ->map(function (Collection $items, string $key) {
                $winner = $items->sort(function (array $left, array $right) {
                    $leftDate = $left['fecha_ult_actuacion'] ?: $left['fecha_etapa'] ?: '0000-00-00';
                    $rightDate = $right['fecha_ult_actuacion'] ?: $right['fecha_etapa'] ?: '0000-00-00';

                    return [$rightDate, $right['_row']] <=> [$leftDate, $left['_row']];
                })->first();

                return [
                    'key' => $key,
                    'winner' => $winner,
                    'rows' => $items->values()->all(),
                    'source_ids' => $items->pluck('id_proceso')->sort()->values()->all(),
                ];
            })
            ->values();
    }

    private function mapGroupsToCases(Collection $groups, Collection $cases): array
    {
        $matches = collect();
        $errors = collect();
        $usedCaseIds = [];

        foreach ($groups as $group) {
            $ranked = $cases
                ->map(fn (Caso $case) => [
                    'case' => $case,
                    'score' => $this->caseScore($case, $group),
                ])
                ->sort(function (array $left, array $right) {
                    if ($left['score'] !== $right['score']) {
                        return $right['score'] <=> $left['score'];
                    }

                    return $left['case']->id <=> $right['case']->id;
                })
                ->values();

            $overrideIds = collect($group['source_ids'])
                ->map(fn (string $id) => self::VERIFIED_CASE_OVERRIDES[$id] ?? null)
                ->filter()
                ->unique()
                ->values();
            $forcedCaseId = $overrideIds->count() === 1 ? (int) $overrideIds->first() : null;
            $best = $forcedCaseId
                ? $ranked->first(fn (array $candidate) => $candidate['case']->id === $forcedCaseId)
                : $ranked->first();
            $second = $forcedCaseId ? null : $ranked->get(1);
            $reason = null;

            if ($overrideIds->count() > 1) {
                $reason = 'el grupo contiene excepciones verificadas incompatibles';
            } elseif (! $best) {
                $reason = $forcedCaseId
                    ? "no existe el Caso verificado {$forcedCaseId}"
                    : 'sin candidato';
            } elseif ($forcedCaseId && $best['score'] < 80) {
                $reason = "el Caso verificado {$forcedCaseId} ya no conserva documento y nombre coincidentes";
            } elseif (! $forcedCaseId && $best['score'] < 150) {
                $reason = 'sin candidato con evidencia suficiente';
            } elseif ($second && $best['score'] === $second['score']) {
                $reason = "empate entre casos {$best['case']->id} y {$second['case']->id}";
            } elseif ($best['case']->trashed()) {
                $reason = "el Caso {$best['case']->id} está eliminado y requiere restauración explícita";
            } elseif (isset($usedCaseIds[$best['case']->id])) {
                $reason = "el Caso {$best['case']->id} ya fue asignado al grupo {$usedCaseIds[$best['case']->id]}";
            }

            if ($reason !== null) {
                $errors->push([
                    'source_ids' => $group['source_ids'],
                    'radicado' => $group['winner']['radicado'],
                    'documento' => $group['winner']['id_deudor'],
                    'razon' => $reason,
                    'mejor_puntaje' => $best['score'] ?? null,
                ]);
                continue;
            }

            $usedCaseIds[$best['case']->id] = implode(',', $group['source_ids']);
            $matches->push([
                'case' => $best['case'],
                'group' => $group,
                'score' => $best['score'],
            ]);
        }

        return compact('matches', 'errors');
    }

    private function caseScore(Caso $case, array $group): int
    {
        $winner = $group['winner'];
        $sourceIds = $group['source_ids'];
        $score = 0;

        if ($winner['radicado'] !== '' && $this->radicado($case->radicado) === $winner['radicado']) {
            $score += 200;
        }

        if (collect($sourceIds)->contains(fn (string $id) => $this->notesContainSourceId((string) $case->notas_legales, $id))) {
            $score += 150;
        }

        if (in_array($this->identifier($case->referencia_credito), $sourceIds, true)) {
            $score += 130;
        }

        $documents = collect($group['rows'])->pluck('id_deudor')->filter()->unique()->all();
        if ($case->deudor && in_array($this->identifier($case->deudor->numero_documento), $documents, true)) {
            $score += 50;
        }

        $names = collect($group['rows'])
            ->pluck('nombre_deudor')
            ->map(fn ($name) => $this->normalizeText($name))
            ->filter()
            ->unique()
            ->all();
        if ($case->deudor && in_array($this->normalizeText($case->deudor->nombre_completo), $names, true)) {
            $score += 30;
        }

        if ($winner['etapa_procesal'] !== '' && trim((string) $case->etapa_procesal) === $winner['etapa_procesal']) {
            $score += 15;
        }

        $latestDate = $winner['fecha_ult_actuacion'] ?: $winner['fecha_etapa'];
        if ($latestDate && $this->modelDate($case->ultima_actividad) === $latestDate) {
            $score += 10;
        }

        if ($case->trashed()) {
            $score -= 100;
        }

        if ($this->identifier($case->referencia_credito) !== '') {
            $score += 5;
        }

        return $score;
    }

    private function notesContainSourceId(string $notes, string $id): bool
    {
        return (bool) preg_match(
            '/(?:id_proceso|ID(?:\(s\))?\s+proceso\s*:)[^\r\n]*\b'.preg_quote($id, '/').'\b/iu',
            $notes,
        );
    }

    private function buildJudgeIndex(Collection $judges): array
    {
        $byCode = [];
        $byName = [];

        foreach ($judges as $judge) {
            $code = $this->judgeCode($judge->nombre);
            if ($code !== null) {
                $byCode[$code][] = $judge;
            }

            $name = $this->normalizeJudgeName($judge->nombre);
            if ($name !== '') {
                $byName[$name][] = $judge;
            }
        }

        return compact('judges', 'byCode', 'byName');
    }

    private function resolveJudge(Caso $case, array $winner, array $index): array
    {
        $wantedName = trim((string) ($winner['nombre_juzgado_actual'] ?: $winner['nombre_juzgado_original']));
        if ($wantedName === '') {
            return ['id' => null, 'unresolved' => false, 'wanted_name' => ''];
        }

        $wantedNormalized = $this->normalizeJudgeName($wantedName);
        $currentName = (string) ($case->juzgado?->nombre ?? '');
        $currentCode = $this->judgeCode($currentName);
        $wantedCode = strlen($winner['radicado']) === 23 ? substr($winner['radicado'], 0, 12) : null;

        if ($case->juzgado_id && (
            ($wantedCode !== null && $currentCode === $wantedCode)
            || $this->normalizeJudgeName($currentName) === $wantedNormalized
        )) {
            return ['id' => (int) $case->juzgado_id, 'unresolved' => false, 'wanted_name' => $wantedName];
        }

        if ($wantedCode !== null && count($index['byCode'][$wantedCode] ?? []) === 1) {
            return [
                'id' => (int) $index['byCode'][$wantedCode][0]->id,
                'unresolved' => false,
                'wanted_name' => $wantedName,
            ];
        }

        $exact = $index['byName'][$wantedNormalized] ?? [];
        if (count($exact) === 1) {
            return ['id' => (int) $exact[0]->id, 'unresolved' => false, 'wanted_name' => $wantedName];
        }

        $contains = $index['judges']->filter(function (Juzgado $judge) use ($wantedNormalized) {
            $candidate = $this->normalizeJudgeName($judge->nombre);

            return strlen($candidate) >= 12
                && strlen($wantedNormalized) >= 12
                && (str_contains($candidate, $wantedNormalized) || str_contains($wantedNormalized, $candidate));
        })->values();

        if ($contains->count() === 1) {
            return ['id' => (int) $contains->first()->id, 'unresolved' => false, 'wanted_name' => $wantedName];
        }

        $alreadyEquivalent = $case->juzgado_id
            && $wantedCode === null
            && $this->normalizeJudgeName($currentName) !== ''
            && (
                str_contains($this->normalizeJudgeName($currentName), $wantedNormalized)
                || str_contains($wantedNormalized, $this->normalizeJudgeName($currentName))
            );

        return [
            'id' => $alreadyEquivalent ? (int) $case->juzgado_id : null,
            'unresolved' => ! $alreadyEquivalent,
            'wanted_name' => $wantedName,
        ];
    }

    private function calculateChanges(Caso $case, array $winner, ?int $judgeId): array
    {
        $changes = [];

        if (strlen($winner['radicado']) === 23 && $this->radicado($case->radicado) !== $winner['radicado']) {
            $changes['radicado'] = $winner['radicado'];
        }

        if ($winner['etapa_procesal'] !== '') {
            foreach (['etapa_actual', 'etapa_procesal'] as $field) {
                if (trim((string) $case->{$field}) !== $winner['etapa_procesal']) {
                    $changes[$field] = $winner['etapa_procesal'];
                }
            }
        }

        $latestDate = $winner['fecha_ult_actuacion'] ?: $winner['fecha_etapa'];
        if ($latestDate !== null && $this->modelDate($case->ultima_actividad) !== $latestDate) {
            $changes['ultima_actividad'] = $latestDate;
        }

        if ($judgeId !== null && (int) $case->juzgado_id !== $judgeId) {
            $changes['juzgado_id'] = $judgeId;
        }

        return $changes;
    }

    private function applyChanges(Collection $planned, string $sourceName): void
    {
        DB::transaction(function () use ($planned, $sourceName) {
            foreach ($planned as $item) {
                /** @var Caso $case */
                $case = Caso::withTrashed()->lockForUpdate()->findOrFail($item['case']->id);
                $changes = $item['changes'];
                $before = collect(array_keys($changes))->mapWithKeys(fn (string $field) => [
                    $field => $this->auditValue($case->{$field}),
                ])->all();

                $case->fill($changes);
                $marker = 'Sincronizado desde '.$sourceName.' — ID(s) proceso: '.implode(', ', $item['group']['source_ids']);
                if (! str_contains((string) $case->notas_legales, $marker)) {
                    $trace = [
                        $marker,
                        'Campos actualizados: '.implode(', ', array_keys($changes)),
                    ];
                    $case->notas_legales = trim((string) $case->notas_legales)."\n\n".implode("\n", $trace);
                }
                $case->save();

                $after = collect(array_keys($changes))->mapWithKeys(fn (string $field) => [
                    $field => $this->auditValue($case->{$field}),
                ])->all();

                AuditoriaEvento::create([
                    'user_id' => null,
                    'evento' => 'ACTUALIZAR_CASO_MIS_PROCESOS',
                    'descripcion_breve' => "Caso #{$case->id} sincronizado desde {$sourceName}",
                    'auditable_id' => $case->id,
                    'auditable_type' => Caso::class,
                    'criticidad' => 'media',
                    'detalle_anterior' => $before,
                    'detalle_nuevo' => $after,
                    'user_agent' => 'Comando casos:actualizar-mis-procesos',
                ]);
            }
        });
    }

    private function serializeChanges(Collection $planned): array
    {
        return $planned->map(function (array $item) {
            $case = $item['case'];
            $changes = [];

            foreach ($item['changes'] as $field => $newValue) {
                $oldValue = $field === 'juzgado_id'
                    ? ['id' => $case->juzgado_id, 'nombre' => $case->juzgado?->nombre]
                    : $this->auditValue($case->{$field});
                $serializedNew = $field === 'juzgado_id'
                    ? ['id' => $newValue, 'nombre' => Juzgado::find($newValue)?->nombre]
                    : $newValue;

                $changes[$field] = ['antes' => $oldValue, 'despues' => $serializedNew];
            }

            return [
                'caso_id' => $case->id,
                'ids_proceso' => $item['group']['source_ids'],
                'puntaje_mapeo' => $item['score'],
                'campos' => $changes,
            ];
        })->values()->all();
    }

    private function failWithMappingErrors(
        string $path,
        Collection $rows,
        Collection $groups,
        array $mapping,
        Collection $duplicates,
    ): int {
        $report = [
            'ok' => false,
            'archivo' => basename($path),
            'filas' => $rows->count(),
            'casos_logicos' => $groups->count(),
            'casos_mapeados' => $mapping['matches']->count(),
            'casos_faltantes_o_ambiguos' => $mapping['errors']->all(),
            'radicados_duplicados_consolidados' => $duplicates->all(),
            'aplicados' => 0,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->error('La sincronización fue bloqueada porque el mapeo no es inequívoco.');
            $this->table(
                ['IDs proceso', 'Radicado', 'Documento', 'Razón', 'Puntaje'],
                $mapping['errors']->map(fn (array $error) => [
                    implode(', ', $error['source_ids']),
                    $error['radicado'] ?: '—',
                    $error['documento'] ?: '—',
                    $error['razon'],
                    $error['mejor_puntaje'] ?? '—',
                ])->all(),
            );
        }

        return self::FAILURE;
    }

    private function renderReport(array $report): void
    {
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return;
        }

        $this->newLine();
        $this->info($this->option('apply') ? 'Sincronización aplicada.' : 'Simulación completada; no se modificó la base.');
        $this->table(['Métrica', 'Resultado'], [
            ['Filas del Excel', $report['filas']],
            ['IDs externos únicos', $report['ids_proceso_unicos']],
            ['Casos lógicos', $report['casos_logicos']],
            ['Casos mapeados', $report['casos_mapeados']],
            ['Casos faltantes', $report['casos_faltantes']],
            ['Casos por actualizar', $report['casos_por_actualizar']],
            ['Casos al día', $report['casos_al_dia']],
            ['Cambios aplicados', $report['aplicados']],
        ]);

        if ($report['cambios'] !== []) {
            $this->table(
                ['Caso', 'ID(s) proceso', 'Campos'],
                collect($report['cambios'])->map(fn (array $change) => [
                    '#'.$change['caso_id'],
                    implode(', ', $change['ids_proceso']),
                    implode(', ', array_keys($change['campos'])),
                ])->all(),
            );
        }

        if ($report['juzgados_no_resueltos'] !== []) {
            $this->warn('Hay juzgados del origen que no pudieron resolverse de forma inequívoca; no se modificaron:');
            $this->table(
                ['Caso', 'ID(s) proceso', 'Juzgado origen'],
                collect($report['juzgados_no_resueltos'])->map(fn (array $warning) => [
                    '#'.$warning['caso_id'],
                    implode(', ', $warning['source_ids']),
                    $warning['juzgado'],
                ])->all(),
            );
        }
    }

    private function normalizeHeader(mixed $value): string
    {
        return Str::of((string) $value)->ascii()->lower()->trim()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
    }

    private function identifier(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $string = trim((string) $value);

        return preg_match('/^\d+\.0+$/', $string) ? strstr($string, '.', true) : preg_replace('/\s+/', '', $string);
    }

    private function radicado(mixed $value): string
    {
        return preg_replace('/\D+/', '', $this->identifier($value));
    }

    private function date(mixed $value, int $row, string $field): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if (is_numeric($value)) {
            return CarbonImmutable::instance(
                \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value),
            )->format('Y-m-d');
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            $date = CarbonImmutable::createFromFormat('!'.$format, trim((string) $value));
            if ($date !== false && $date->format($format) === trim((string) $value)) {
                return $date->format('Y-m-d');
            }
        }

        throw new RuntimeException("Fecha inválida en {$field}, fila {$row}: {$value}");
    }

    private function modelDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof \DateTimeInterface
            ? $value->format('Y-m-d')
            : CarbonImmutable::parse((string) $value)->format('Y-m-d');
    }

    private function normalizeText(mixed $value): string
    {
        return preg_replace('/[^A-Z0-9]+/', '', Str::upper(Str::ascii(trim((string) $value))));
    }

    private function normalizeJudgeName(mixed $value): string
    {
        $withoutCode = preg_replace('/^\s*\(\d{12}\)\s*/', '', trim((string) $value));

        return $this->normalizeText($withoutCode);
    }

    private function judgeCode(mixed $value): ?string
    {
        return preg_match('/^\s*\((\d{12})\)/', trim((string) $value), $matches)
            ? $matches[1]
            : null;
    }

    private function auditValue(mixed $value): mixed
    {
        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
    }
}
