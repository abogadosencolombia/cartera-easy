<?php

namespace App\Services;

use App\Models\Caso;
use App\Models\ProcesoRadicado;
use App\Models\RequisitoDocumento;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ExpedienteIntegrityService
{
    private array $columnSupportCache = [];

    public function refresh(Caso|ProcesoRadicado $expediente): array
    {
        $summary = $expediente instanceof Caso
            ? $this->analyzeCaso($expediente)
            : $this->analyzeProceso($expediente);

        $payload = [
            'integridad_score' => $summary['score'],
            'integridad_resumen' => $summary,
        ];

        $expediente->forceFill($payload);

        if (!$this->supportsPersistence($expediente->getTable())) {
            return $summary;
        }

        $usesTimestamps = $expediente->usesTimestamps();
        try {
            $expediente->timestamps = false;
            $expediente->saveQuietly();
        } finally {
            $expediente->timestamps = $usesTimestamps;
        }

        return $summary;
    }

    public function refreshMissing(iterable $expedientes, bool $force = false): void
    {
        foreach ($expedientes as $expediente) {
            if (!$expediente instanceof Caso && !$expediente instanceof ProcesoRadicado) {
                continue;
            }

            if ($force || blank($expediente->getAttribute('integridad_resumen'))) {
                $this->refresh($expediente);
            }
        }
    }

    public function supportsPersistence(string $table): bool
    {
        return $this->columnSupportCache[$table] ??= Schema::hasColumn($table, 'integridad_score')
            && Schema::hasColumn($table, 'integridad_resumen');
    }

    public function analyzeCaso(Caso $caso): array
    {
        $caso->loadMissing([
            'deudor',
            'codeudores',
            'cooperativa',
            'user',
            'users',
            'juzgado',
            'documentos',
            'validacionesLegales.requisito',
            'actuaciones',
            'tareas',
        ]);

        $checks = [];
        $avisos = [];
        $documentosFaltantes = $this->missingRequiredCasoDocuments($caso);
        $latestActuacion = $caso->actuaciones->sortByDesc('fecha_actuacion')->first();
        $diasSinActuacion = $latestActuacion?->fecha_actuacion
            ? Carbon::parse($latestActuacion->fecha_actuacion)->diffInDays(now())
            : null;

        $this->check($checks, (bool) $caso->deudor, 10, [
            'titulo' => 'Sin deudor principal vinculado',
            'detalle' => 'El expediente no tiene sujeto principal para validar notificaciones, cartera y documentos.',
            'prioridad' => 'alta',
            'accion' => 'Editar datos',
            'accion_tipo' => 'edit',
        ], 'Deudor identificado');

        $this->check($checks, $caso->deudor && !$this->isPlaceholderPersona($caso->deudor), 8, [
            'titulo' => 'Datos del deudor incompletos',
            'detalle' => 'Falta documento real o nombre definitivo del deudor.',
            'prioridad' => 'alta',
            'accion' => 'Editar deudor',
            'accion_tipo' => 'edit',
        ], 'Documento del deudor validado');

        $this->check($checks, $caso->deudor && $this->hasContact($caso->deudor), 5, [
            'titulo' => 'Canales de contacto del deudor débiles',
            'detalle' => 'Registra correo o celular para notificación, cobro y trazabilidad.',
            'prioridad' => 'media',
            'accion' => 'Completar contacto',
            'accion_tipo' => 'edit',
        ], 'Contacto del deudor disponible');

        $this->check($checks, $caso->users->isNotEmpty() || (bool) $caso->user, 8, [
            'titulo' => 'Sin responsable asignado',
            'detalle' => 'El caso debe tener al menos un abogado o gestor responsable.',
            'prioridad' => 'alta',
            'accion' => 'Asignar responsable',
            'accion_tipo' => 'edit',
        ], 'Responsable asignado');

        $this->check($checks, filled($caso->tipo_proceso), 5, [
            'titulo' => 'Tipo de proceso pendiente',
            'detalle' => 'Sin tipo de proceso no se pueden aplicar bien los requisitos y protocolos.',
            'prioridad' => 'media',
            'accion' => 'Editar proceso',
            'accion_tipo' => 'edit',
        ], 'Tipo de proceso definido');

        $this->check($checks, filled($caso->etapa_procesal), 5, [
            'titulo' => 'Etapa procesal pendiente',
            'detalle' => 'La guía de gestión necesita una etapa para sugerir tareas correctas.',
            'prioridad' => 'media',
            'accion' => 'Editar etapa',
            'accion_tipo' => 'edit',
        ], 'Etapa procesal definida');

        $this->check($checks, (bool) $caso->juzgado, 5, [
            'titulo' => 'Juzgado sin asignar',
            'detalle' => 'Falta despacho para ubicar actuaciones, vencimientos y contactos judiciales.',
            'prioridad' => 'media',
            'accion' => 'Asignar juzgado',
            'accion_tipo' => 'edit',
        ], 'Juzgado asignado');

        $this->check($checks, filled($caso->radicado), 4, [
            'titulo' => 'Radicado pendiente',
            'detalle' => 'Guarda el radicado oficial o identificador equivalente cuando exista.',
            'prioridad' => 'media',
            'accion' => 'Registrar radicado',
            'accion_tipo' => 'edit',
        ], 'Radicado registrado');

        $garantiaCodeudor = Str::contains($this->normalize($caso->tipo_garantia_asociada), 'codeudor');
        $codeudoresOk = !$garantiaCodeudor || $caso->sin_codeudores || $caso->codeudores->isNotEmpty();
        $this->check($checks, $codeudoresOk, 8, [
            'titulo' => 'Garantía por codeudor sin soporte completo',
            'detalle' => 'Vincula codeudores o confirma formalmente que el título no tiene garantías adicionales.',
            'prioridad' => 'alta',
            'accion' => 'Editar garantías',
            'accion_tipo' => 'edit',
        ], 'Garantías revisadas');

        $this->check($checks, $documentosFaltantes->isEmpty(), 16, [
            'titulo' => 'Documentos obligatorios faltantes',
            'detalle' => $documentosFaltantes->take(4)->implode(', '),
            'prioridad' => 'alta',
            'accion' => 'Cargar soportes',
            'accion_tipo' => 'tab',
            'tab' => 'documentos',
        ], 'Documentos obligatorios completos');

        $this->check($checks, $caso->documentos->isNotEmpty(), 5, [
            'titulo' => 'Expediente sin soportes cargados',
            'detalle' => 'Carga al menos los soportes base para evitar expedientes vacíos.',
            'prioridad' => 'media',
            'accion' => 'Cargar documentos',
            'accion_tipo' => 'tab',
            'tab' => 'documentos',
        ], 'Soportes cargados');

        $this->check($checks, filled($caso->monto_total) && (float) $caso->monto_total > 0, 6, [
            'titulo' => 'Valor de obligación pendiente',
            'detalle' => 'El monto permite priorizar, liquidar y reportar la gestión.',
            'prioridad' => 'media',
            'accion' => 'Editar financiero',
            'accion_tipo' => 'edit',
        ], 'Valor de obligación registrado');

        $this->check($checks, filled($caso->fecha_apertura), 4, [
            'titulo' => 'Fecha de apertura pendiente',
            'detalle' => 'La fecha base ayuda a medir mora, oportunidad y trazabilidad.',
            'prioridad' => 'media',
            'accion' => 'Editar fechas',
            'accion_tipo' => 'edit',
        ], 'Fecha de apertura registrada');

        $seguimientoOk = !$caso->estaEnSeguimiento()
            || ($latestActuacion && ($diasSinActuacion === null || $diasSinActuacion <= 20));
        $this->check($checks, $seguimientoOk, 7, [
            'titulo' => 'Caso sin actuación reciente',
            'detalle' => $diasSinActuacion === null
                ? 'No hay actuaciones registradas.'
                : "La última actuación tiene {$diasSinActuacion} días.",
            'prioridad' => 'media',
            'accion' => 'Registrar actuación',
            'accion_tipo' => 'tab',
            'tab' => 'actuaciones',
        ], 'Seguimiento procesal activo');

        if ($caso->fecha_vencimiento && Carbon::parse($caso->fecha_vencimiento)->startOfDay()->lt(today())) {
            $avisos[] = [
                'titulo' => 'Fecha de vencimiento cumplida',
                'detalle' => 'Revisa si ya aplica impulso, liquidación o cierre según el estado del caso.',
                'prioridad' => 'media',
            ];
        }

        return $this->buildSummary($checks, $avisos);
    }

    public function analyzeProceso(ProcesoRadicado $proceso): array
    {
        $proceso->loadMissing([
            'demandantes',
            'demandados',
            'abogado',
            'responsableRevision',
            'juzgado',
            'tipoProceso',
            'etapaActual',
            'documentos',
            'actuaciones',
            'tareas',
        ]);

        $checks = [];
        $avisos = [];
        $stageDocsMissing = $this->missingStageDocuments($proceso);
        $latestActuacion = $proceso->actuaciones->sortByDesc('fecha_actuacion')->first();
        $diasSinActuacion = $latestActuacion?->fecha_actuacion
            ? Carbon::parse($latestActuacion->fecha_actuacion)->diffInDays(now())
            : null;

        $this->check($checks, filled($proceso->radicado), 8, [
            'titulo' => 'Radicado oficial pendiente',
            'detalle' => 'Registra el radicado o identificador del expediente para consulta judicial.',
            'prioridad' => 'alta',
            'accion' => 'Editar radicado',
            'accion_tipo' => 'edit',
        ], 'Radicado registrado');

        $this->check($checks, (bool) $proceso->tipoProceso, 6, [
            'titulo' => 'Tipo de proceso pendiente',
            'detalle' => 'El tipo de proceso determina revisión, etapas y requisitos.',
            'prioridad' => 'media',
            'accion' => 'Editar proceso',
            'accion_tipo' => 'edit',
        ], 'Tipo de proceso definido');

        $this->check($checks, (bool) $proceso->juzgado, 6, [
            'titulo' => 'Despacho judicial pendiente',
            'detalle' => 'Sin despacho se dificulta el seguimiento del expediente digital.',
            'prioridad' => 'media',
            'accion' => 'Asignar despacho',
            'accion_tipo' => 'edit',
        ], 'Despacho asignado');

        $this->check($checks, (bool) $proceso->etapaActual, 6, [
            'titulo' => 'Etapa procesal pendiente',
            'detalle' => 'La etapa permite medir SLA y sugerir próximas actuaciones.',
            'prioridad' => 'media',
            'accion' => 'Cambiar etapa',
            'accion_tipo' => 'tab',
            'tab' => 'actuaciones',
        ], 'Etapa procesal definida');

        $this->check($checks, (bool) $proceso->abogado, 8, [
            'titulo' => 'Abogado sin asignar',
            'detalle' => 'Asigna responsable directo del expediente.',
            'prioridad' => 'alta',
            'accion' => 'Asignar abogado',
            'accion_tipo' => 'edit',
        ], 'Abogado asignado');

        $this->check($checks, (bool) $proceso->responsableRevision, 4, [
            'titulo' => 'Responsable de revisión pendiente',
            'detalle' => 'Asigna quién vigila la próxima revisión para evitar vencimientos silenciosos.',
            'prioridad' => 'media',
            'accion' => 'Asignar revisor',
            'accion_tipo' => 'edit',
        ], 'Responsable de revisión asignado');

        $fechaRevisionOk = $proceso->fecha_proxima_revision
            && !Carbon::parse($proceso->fecha_proxima_revision)->startOfDay()->lt(today());
        $this->check($checks, $fechaRevisionOk, 10, [
            'titulo' => 'Próxima revisión vencida o sin programar',
            'detalle' => $proceso->fecha_proxima_revision
                ? 'La fecha de próxima revisión ya pasó.'
                : 'No hay una fecha comprometida para volver a revisar el expediente.',
            'prioridad' => 'alta',
            'accion' => 'Registrar actuación',
            'accion_tipo' => 'tab',
            'tab' => 'actuaciones',
        ], 'Próxima revisión vigente');

        $this->check($checks, $proceso->demandantes->isNotEmpty(), 7, [
            'titulo' => 'Demandante pendiente',
            'detalle' => 'El expediente debe tener al menos una parte demandante.',
            'prioridad' => 'alta',
            'accion' => 'Editar partes',
            'accion_tipo' => 'tab',
            'tab' => 'partes',
        ], 'Demandante vinculado');

        $demandadosCompletos = $proceso->demandados->isNotEmpty()
            && !$proceso->info_incompleta
            && $proceso->demandados->every(fn ($persona) => !$this->isPlaceholderPersona($persona));
        $this->check($checks, $demandadosCompletos, 10, [
            'titulo' => 'Demandado por identificar o incompleto',
            'detalle' => 'Completa nombre y documento real del demandado o deja una nota clara de seguimiento.',
            'prioridad' => 'alta',
            'accion' => 'Completar partes',
            'accion_tipo' => 'tab',
            'tab' => 'partes',
        ], 'Demandados identificados');

        $this->check($checks, filled($proceso->link_expediente) || filled($proceso->ubicacion_drive), 5, [
            'titulo' => 'Sin enlace de expediente',
            'detalle' => 'Agrega enlace de Rama Judicial o carpeta Drive para acelerar la revisión.',
            'prioridad' => 'media',
            'accion' => 'Editar enlaces',
            'accion_tipo' => 'edit',
        ], 'Enlace de expediente disponible');

        $this->check($checks, $proceso->documentos->isNotEmpty() && $stageDocsMissing->isEmpty(), 10, [
            'titulo' => 'Soportes procesales pendientes',
            'detalle' => $stageDocsMissing->isNotEmpty()
                ? $stageDocsMissing->implode(', ')
                : 'El repositorio del expediente no tiene documentos.',
            'prioridad' => 'media',
            'accion' => 'Cargar soportes',
            'accion_tipo' => 'tab',
            'tab' => 'documentos',
        ], 'Soportes procesales cargados');

        $seguimientoOk = !$proceso->estaEnSeguimiento()
            || ($latestActuacion && ($diasSinActuacion === null || $diasSinActuacion <= 20));
        $this->check($checks, $seguimientoOk, 7, [
            'titulo' => 'Expediente sin actuación reciente',
            'detalle' => $diasSinActuacion === null
                ? 'No hay actuaciones registradas.'
                : "La última actuación tiene {$diasSinActuacion} días.",
            'prioridad' => 'media',
            'accion' => 'Registrar actuación',
            'accion_tipo' => 'tab',
            'tab' => 'actuaciones',
        ], 'Actuaciones actualizadas');

        $diasParaVencer = $proceso->dias_para_vencer;
        $this->check($checks, $diasParaVencer === null || $diasParaVencer >= 0, 8, [
            'titulo' => 'SLA de etapa vencido',
            'detalle' => 'La etapa actual superó el plazo esperado de gestión.',
            'prioridad' => 'alta',
            'accion' => 'Registrar impulso',
            'accion_tipo' => 'tab',
            'tab' => 'actuaciones',
        ], 'SLA de etapa dentro de plazo');

        if ($diasParaVencer !== null && $diasParaVencer >= 0 && $diasParaVencer <= 2) {
            $avisos[] = [
                'titulo' => 'Etapa próxima a vencer',
                'detalle' => "Quedan {$diasParaVencer} días hábiles para actuar según el SLA de la etapa.",
                'prioridad' => 'media',
            ];
        }

        return $this->buildSummary($checks, $avisos);
    }

    private function check(array &$checks, bool $passes, int $weight, array $missing, string $strength): void
    {
        $checks[] = [
            'passes' => $passes,
            'weight' => $weight,
            'missing' => $missing,
            'strength' => $strength,
        ];
    }

    private function buildSummary(array $checks, array $avisos = []): array
    {
        $total = max(array_sum(array_column($checks, 'weight')), 1);
        $earned = collect($checks)->filter(fn ($check) => $check['passes'])->sum('weight');
        $score = (int) round(($earned / $total) * 100);

        $faltantes = collect($checks)
            ->filter(fn ($check) => !$check['passes'])
            ->pluck('missing')
            ->filter()
            ->sortBy(fn ($item) => $this->priorityRank($item['prioridad'] ?? 'baja'))
            ->values()
            ->all();

        $fortalezas = collect($checks)
            ->filter(fn ($check) => $check['passes'])
            ->pluck('strength')
            ->filter()
            ->take(5)
            ->values()
            ->all();

        $estado = match (true) {
            collect($faltantes)->contains(fn ($item) => ($item['prioridad'] ?? null) === 'alta') || $score < 60 => 'critico',
            $score < 85 || collect($faltantes)->contains(fn ($item) => ($item['prioridad'] ?? null) === 'media') => 'riesgo',
            default => 'completo',
        };

        $first = $faltantes[0] ?? null;

        return [
            'score' => max(0, min(100, $score)),
            'estado' => $estado,
            'faltantes' => $faltantes,
            'avisos' => array_values($avisos),
            'fortalezas' => $fortalezas,
            'proxima_accion' => $first
                ? ($first['accion'] . ': ' . $first['titulo'])
                : 'Mantener revisión periódica y registrar cualquier actuación nueva.',
            'actualizado_en' => now()->toIso8601String(),
        ];
    }

    private function missingRequiredCasoDocuments(Caso $caso): Collection
    {
        $validaciones = $caso->validacionesLegales
            ->filter(fn ($validacion) => $validacion->estado === 'incumple')
            ->map(fn ($validacion) => $validacion->requisito?->tipo_documento_requerido ?: $validacion->observacion)
            ->filter()
            ->values();

        if ($validaciones->isNotEmpty()) {
            return $validaciones;
        }

        if (blank($caso->tipo_proceso)) {
            return collect();
        }

        $requisitos = RequisitoDocumento::whereHas('tipoProceso', function ($query) use ($caso) {
                $query->where('nombre', $caso->tipo_proceso);
            })
            ->where(function ($query) use ($caso) {
                $query->where('cooperativa_id', $caso->cooperativa_id)
                    ->orWhereNull('cooperativa_id');
            })
            ->get();

        if ($requisitos->isEmpty()) {
            return collect();
        }

        $documentos = $caso->documentos
            ->pluck('tipo_documento')
            ->map(fn ($tipo) => $this->normalize($tipo));

        return $requisitos
            ->pluck('tipo_documento_requerido')
            ->filter(fn ($tipo) => !$documentos->contains($this->normalize($tipo)))
            ->values();
    }

    private function missingStageDocuments(ProcesoRadicado $proceso): Collection
    {
        if ($proceso->documentos->isEmpty()) {
            return collect();
        }

        $etapa = $this->normalize($proceso->etapaActual?->nombre);
        $documentsText = $this->normalize($proceso->documentos->map(function ($doc) {
            return trim(($doc->file_name ?? '') . ' ' . ($doc->descripcion ?? ''));
        })->implode(' '));

        $expected = collect();

        if (Str::contains($etapa, 'demanda')) {
            $expected->push(['Demanda o constancia de radicación', ['demanda', 'radic']]);
        }

        if (Str::contains($etapa, 'mandamiento')) {
            $expected->push(['Auto o mandamiento de pago', ['auto', 'mandamiento']]);
        }

        if (Str::contains($etapa, 'sentencia')) {
            $expected->push(['Sentencia o fallo', ['sentencia', 'fallo']]);
        }

        if ($expected->isEmpty()) {
            return collect();
        }

        return $expected
            ->filter(function ($item) use ($documentsText) {
                return !collect($item[1])->contains(fn ($needle) => Str::contains($documentsText, $needle));
            })
            ->map(fn ($item) => $item[0])
            ->values();
    }

    private function isPlaceholderPersona(?Model $persona): bool
    {
        if (!$persona) {
            return true;
        }

        $nombre = $this->normalize($persona->nombre_completo ?? '');
        $documento = strtoupper(trim((string) ($persona->numero_documento ?? '')));

        return blank($persona->nombre_completo)
            || blank($documento)
            || Str::startsWith($documento, 'TEMP-')
            || Str::contains($nombre, 'por identificar');
    }

    private function hasContact(Model $persona): bool
    {
        return filled($persona->celular_1 ?? null)
            || filled($persona->celular_2 ?? null)
            || filled($persona->correo_1 ?? null)
            || filled($persona->correo_2 ?? null)
            || filled($persona->celular ?? null)
            || filled($persona->correo ?? null);
    }

    private function normalize(?string $value): string
    {
        return Str::of((string) $value)
            ->ascii()
            ->lower()
            ->squish()
            ->toString();
    }

    private function priorityRank(string $priority): int
    {
        return match ($priority) {
            'alta' => 0,
            'media' => 1,
            default => 2,
        };
    }
}
