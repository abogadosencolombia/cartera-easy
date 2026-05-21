<?php

namespace Database\Seeders;

use App\Models\EtapaProcesal;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EtapasProcesalesSeeder extends Seeder
{
    private const CSV_ETAPA_COLUMN = 3;

    public function run(): void
    {
        $this->command->info('Actualizando etapas procesales incrementalmente (SIN BORRADO)...');

        $etapasCsv = $this->etapasDesdeCsv();
        $etapas = $this->consolidarEtapas(array_merge(
            $this->catalogoBase(),
            $etapasCsv,
        ));

        DB::transaction(function () use ($etapas) {
            foreach ($etapas as $index => $data) {
                $metadata = $this->metadataPara($data['nombre']);

                EtapaProcesal::updateOrCreate(
                    ['nombre' => $data['nombre']],
                    [
                        'modulo_id' => $data['modulo_id'] ?? $metadata['modulo_id'],
                        'sla_dias' => $data['sla_dias'] ?? $metadata['sla_dias'],
                        'riesgo' => $data['riesgo'] ?? $metadata['riesgo'],
                        'responsable' => $data['responsable'] ?? $metadata['responsable'],
                        'descripcion' => $data['descripcion'] ?? $metadata['descripcion'],
                        'orden' => $index + 1,
                    ]
                );
            }
        });

        $this->command->info(sprintf(
            'Se han actualizado %d etapas procesales (%d detectadas desde CSV).',
            count($etapas),
            count($etapasCsv)
        ));
    }

    private function catalogoBase(): array
    {
        return [
            // FASE 0: PREPROCESAL / MASC
            $this->etapa('RECEPCIÓN DE DOCUMENTOS', 1, 2, 'BAJO', 'CLIENTE'),
            $this->etapa('RECEPCIÓN DE DOCUMENTOS PARCIAL', 1, 2, 'MEDIO', 'CLIENTE'),
            $this->etapa('ESTUDIO DE VIABILIDAD JURÍDICA', 1, 3, 'BAJO', 'ABOGADO'),
            $this->etapa('INVESTIGACIÓN DE BIENES', 1, 5, 'BAJO', 'ABOGADO'),
            $this->etapa('INVESTIGACIÓN DE BIENES POSITIVA', 1, 1, 'BAJO', 'ABOGADO'),
            $this->etapa('INVESTIGACIÓN DE BIENES NEGATIVA', 1, 3, 'MEDIO', 'ABOGADO'),
            $this->etapa('CONCILIACIÓN EXTRAJUDICIAL COMO REQUISITO DE PROCEDIBILIDAD', 1, 30, 'MEDIO', 'ABOGADO'),
            $this->etapa('SOLICITUD DE CONCILIACIÓN', 1, 3, 'BAJO', 'ABOGADO'),
            $this->etapa('CITACIÓN A AUDIENCIA DE CONCILIACIÓN', 1, 5, 'BAJO', 'ABOGADO'),
            $this->etapa('AUDIENCIA DE CONCILIACIÓN', 7, 30, 'MEDIO', 'JUZGADO'),
            $this->etapa('ACTA DE CONCILIACIÓN', 7, 1, 'BAJO', 'ABOGADO'),
            $this->etapa('CONSTANCIA DE NO ACUERDO', 7, 1, 'MEDIO', 'ABOGADO'),
            $this->etapa('ACUERDO CONCILIATORIO', 7, 1, 'BAJO', 'ABOGADO'),
            $this->etapa('INCUMPLIMIENTO DE ACUERDO CONCILIATORIO', 7, 5, 'MUY_ALTO', 'ABOGADO'),
            $this->etapa('ACUERDO DE PAGO PREJUDICIAL', 1, 5, 'BAJO', 'ABOGADO'),
            $this->etapa('SIN DEMANDA POR ACUERDO DE PAGO', 8, 0, 'BAJO', 'SISTEMA'),

            // FASE 1: POSTULACIÓN
            $this->etapa('POR PRESENTAR', 2, 3, 'MEDIO', 'ABOGADO'),
            $this->etapa('ELABORACIÓN DE DEMANDA', 2, 5, 'BAJO', 'ABOGADO'),
            $this->etapa('DEMANDA PRESENTADA', 2, 1, 'BAJO', 'SISTEMA'),
            $this->etapa('REPARTO / ASIGNACIÓN JUZGADO', 2, 3, 'BAJO', 'JUZGADO'),
            $this->etapa('AVOCA CONOCIMIENTO', 2, 3, 'BAJO', 'JUZGADO'),
            $this->etapa('CALIFICACIÓN DE DEMANDA', 2, 5, 'BAJO', 'JUZGADO'),
            $this->etapa('CALIFICACIÓN: ADMITIDA', 2, 10, 'BAJO', 'JUZGADO'),
            $this->etapa('CALIFICACIÓN: INADMITIDA', 2, 5, 'MEDIO', 'ABOGADO'),
            $this->etapa('CALIFICACIÓN: RECHAZADA', 2, 3, 'ALTO', 'ABOGADO'),
            $this->etapa('SUBSANACIÓN DE DEMANDA', 2, 5, 'MEDIO', 'ABOGADO'),
            $this->etapa('REFORMA DE DEMANDA', 2, 5, 'MEDIO', 'ABOGADO'),
            $this->etapa('RETIRO DE DEMANDA', 8, 0, 'MEDIO', 'ABOGADO'),
            $this->etapa('RECHAZADA POR COMPETENCIA', 2, 5, 'ALTO', 'ABOGADO'),
            $this->etapa('REQUIERE PREVIO DESISTIMIENTO', 2, 5, 'ALTO', 'ABOGADO'),

            // FASE 2: NOTIFICACIÓN
            $this->etapa('NOTIFICACIÓN LEY 2213 (MENSAJE DE DATOS)', 3, 2, 'BAJO', 'ABOGADO'),
            $this->etapa('NOTIFICACIÓN PERSONAL (ART. 291 CGP)', 3, 5, 'BAJO', 'ABOGADO'),
            $this->etapa('NOTIFICACIÓN POR AVISO (ART. 292 CGP)', 3, 5, 'BAJO', 'ABOGADO'),
            $this->etapa('NOTIFICACIÓN POR ESTADO', 3, 1, 'BAJO', 'JUZGADO'),
            $this->etapa('PENDIENTE DE NOTIFICACIÓN', 3, 5, 'MEDIO', 'ABOGADO'),
            $this->etapa('PROCESO DE NOTIFICACIÓN', 3, 5, 'BAJO', 'ABOGADO'),
            $this->etapa('CLIENTE NOTIFICADO', 3, 1, 'BAJO', 'CLIENTE'),
            $this->etapa('NOTIFICACIÓN A ACREEDORES', 3, 5, 'MEDIO', 'ABOGADO'),
            $this->etapa('EMPLAZAMIENTO', 3, 15, 'MEDIO', 'ABOGADO'),
            $this->etapa('NOMBRAMIENTO CURADOR AD LITEM', 3, 10, 'MEDIO', 'JUZGADO'),

            // FASE 3: CONTRADICCIÓN Y DEFENSA
            $this->etapa('TÉRMINO DE TRASLADO CORRIENDO', 3, 20, 'BAJO', 'CONTRAPARTE'),
            $this->etapa('CONTESTACIÓN DE DEMANDA', 3, 1, 'MEDIO', 'CONTRAPARTE'),
            $this->etapa('EXCEPCIONES DE MÉRITO / PREVIAS', 3, 10, 'ALTO', 'CONTRAPARTE'),
            $this->etapa('TRASLADO EXCEPCIONES', 3, 5, 'MEDIO', 'ABOGADO'),
            $this->etapa('PRONUNCIAMIENTO DE EXCEPCIONES', 3, 5, 'ALTO', 'ABOGADO'),
            $this->etapa('NO PROPONE EXCEPCIONES', 3, 1, 'BAJO', 'CONTRAPARTE'),
            $this->etapa('DEMANDA DE RECONVENCIÓN', 3, 20, 'ALTO', 'CONTRAPARTE'),
            $this->etapa('LLAMAMIENTO EN GARANTÍA', 3, 10, 'MEDIO', 'ABOGADO'),

            // FASE 4: AUDIENCIAS Y PRUEBAS
            $this->etapa('AUDIENCIA INICIAL (ART. 372 CGP)', 4, 30, 'ALTO', 'JUZGADO'),
            $this->etapa('AUDIENCIA INSTRUCCIÓN Y JUZGAMIENTO (ART. 373 CGP)', 4, 60, 'MUY_ALTO', 'JUZGADO'),
            $this->etapa('AUDIENCIA ÚNICA (VERBAL SUMARIO)', 4, 30, 'MUY_ALTO', 'JUZGADO'),
            $this->etapa('AUDIENCIA DE TRÁMITE', 4, 30, 'ALTO', 'JUZGADO'),
            $this->etapa('DECRETA PRUEBAS', 4, 10, 'MEDIO', 'JUZGADO'),
            $this->etapa('PRÁCTICA DE PRUEBAS', 4, 30, 'ALTO', 'JUZGADO'),
            $this->etapa('INSPECCIÓN JUDICIAL', 4, 15, 'ALTO', 'JUZGADO'),
            $this->etapa('ALEGATOS DE CONCLUSIÓN', 4, 5, 'ALTO', 'ABOGADO'),

            // FASE 5: SENTENCIA Y RECURSOS
            $this->etapa('SENTENCIA DE PRIMERA INSTANCIA', 5, 1, 'MUY_ALTO', 'JUZGADO'),
            $this->etapa('SENTENCIA', 5, 1, 'MUY_ALTO', 'JUZGADO'),
            $this->etapa('ACLARACIÓN / CORRECCIÓN / ADICIÓN DE PROVIDENCIA', 5, 3, 'MEDIO', 'ABOGADO'),
            $this->etapa('RECURSO DE REPOSICIÓN', 5, 3, 'ALTO', 'ABOGADO'),
            $this->etapa('RECURSO DE APELACIÓN', 5, 5, 'ALTO', 'ABOGADO'),
            $this->etapa('RECURSO DE APELACIÓN / REPOSICIÓN', 5, 5, 'ALTO', 'ABOGADO'),
            $this->etapa('TRASLADO RECURSO', 5, 3, 'MEDIO', 'JUZGADO'),
            $this->etapa('ADMITE APELACIÓN', 5, 5, 'MEDIO', 'JUZGADO'),
            $this->etapa('RESUELVE RECURSO', 5, 10, 'ALTO', 'JUZGADO'),
            $this->etapa('CONFIRMA PROVIDENCIA', 5, 3, 'ALTO', 'JUZGADO'),
            $this->etapa('SENTENCIA EN FIRME / EJECUTORIADA', 5, 3, 'BAJO', 'SISTEMA'),

            // FASE 6: EJECUCIÓN, CAUTELARES Y PAGO DIRECTO
            $this->etapa('MANDAMIENTO DE PAGO', 6, 10, 'BAJO', 'JUZGADO'),
            $this->etapa('NIEGA MANDAMIENTO DE PAGO', 6, 5, 'ALTO', 'JUZGADO'),
            $this->etapa('CORRIGE MANDAMIENTO DE PAGO', 6, 3, 'MEDIO', 'JUZGADO'),
            $this->etapa('AUTO QUE ORDENA SEGUIR ADELANTE LA EJECUCIÓN', 6, 10, 'ALTO', 'JUZGADO'),
            $this->etapa('MEDIDAS CAUTELARES (EMBARGO/SECUESTRO)', 6, 15, 'MEDIO', 'ABOGADO'),
            $this->etapa('EMBARGO DE BIENES', 6, 10, 'MEDIO', 'JUZGADO'),
            $this->etapa('SECUESTRO DE BIENES', 6, 15, 'MEDIO', 'JUZGADO'),
            $this->etapa('LEVANTAMIENTO DE MEDIDAS CAUTELARES', 6, 5, 'MEDIO', 'JUZGADO'),
            $this->etapa('LIQUIDACIÓN DE CRÉDITO Y COSTAS', 6, 10, 'MEDIO', 'ABOGADO'),
            $this->etapa('TRASLADO DE LIQUIDACIÓN', 6, 3, 'MEDIO', 'JUZGADO'),
            $this->etapa('APRUEBA LIQUIDACIÓN', 6, 5, 'MEDIO', 'JUZGADO'),
            $this->etapa('AVALÚO DE BIENES', 6, 20, 'MEDIO', 'ABOGADO'),
            $this->etapa('REMATE DE BIENES', 6, 60, 'ALTO', 'JUZGADO'),
            $this->etapa('APRUEBA REMATE', 6, 5, 'MEDIO', 'JUZGADO'),
            $this->etapa('ADJUDICACIÓN DE BIENES', 6, 10, 'MEDIO', 'JUZGADO'),
            $this->etapa('ENTREGA DEL BIEN', 6, 10, 'MEDIO', 'JUZGADO'),
            $this->etapa('TRANSFERENCIA DE DOMINIO', 6, 10, 'MEDIO', 'ABOGADO'),
            $this->etapa('SOLICITUD DE APREHENSIÓN', 6, 5, 'MEDIO', 'ABOGADO'),
            $this->etapa('ORDEN DE APREHENSIÓN', 6, 10, 'ALTO', 'JUZGADO'),
            $this->etapa('VEHÍCULO APREHENDIDO', 6, 1, 'MEDIO', 'ABOGADO'),
            $this->etapa('RESTITUCIÓN DEL BIEN', 6, 10, 'MEDIO', 'JUZGADO'),
            $this->etapa('PAGO DIRECTO EN TRÁMITE', 6, 10, 'MEDIO', 'ABOGADO'),
            $this->etapa('SOLICITUD DE TERMINACIÓN PAGO DIRECTO', 8, 3, 'BAJO', 'ABOGADO'),

            // FASE 7: INSOLVENCIA, REORGANIZACIÓN Y LIQUIDACIÓN
            $this->etapa('TRÁMITE DE INSOLVENCIA', 7, 30, 'ALTO', 'ABOGADO'),
            $this->etapa('ADMITE TRÁMITE DE NEGOCIACIÓN DE DEUDAS', 7, 10, 'MEDIO', 'JUZGADO'),
            $this->etapa('AUDIENCIA DE NEGOCIACIÓN DE DEUDAS', 7, 30, 'ALTO', 'JUZGADO'),
            $this->etapa('ACUERDO DE NEGOCIACIÓN DE DEUDAS', 7, 10, 'MEDIO', 'ABOGADO'),
            $this->etapa('APRUEBA ACUERDO DE NEGOCIACIÓN DE DEUDAS', 7, 10, 'MEDIO', 'JUZGADO'),
            $this->etapa('DECLARA INCUMPLIMIENTO AL ACUERDO DE NEGOCIACIÓN DE DEUDAS', 7, 5, 'MUY_ALTO', 'JUZGADO'),
            $this->etapa('ADMITE LIQUIDACIÓN PATRIMONIAL', 7, 10, 'ALTO', 'JUZGADO'),
            $this->etapa('ORDENAN LIQUIDACIÓN PATRIMONIAL', 7, 10, 'ALTO', 'JUZGADO'),
            $this->etapa('RECHAZA LIQUIDACIÓN PATRIMONIAL', 7, 5, 'ALTO', 'JUZGADO'),
            $this->etapa('PRESENTACIÓN DE ACREENCIAS', 7, 10, 'MEDIO', 'ABOGADO'),
            $this->etapa('TRASLADO PROYECTO DE CALIFICACIÓN Y GRADUACIÓN DE CRÉDITOS', 7, 5, 'MEDIO', 'JUZGADO'),
            $this->etapa('AUDIENCIA DE GRADUACIÓN Y CALIFICACIÓN DE CRÉDITOS', 7, 30, 'ALTO', 'JUZGADO'),
            $this->etapa('OBJECIONES AL PROYECTO DE GRADUACIÓN Y CALIFICACIÓN', 7, 5, 'ALTO', 'CONTRAPARTE'),
            $this->etapa('AUDIENCIA RESOLUCIÓN DE OBJECIONES', 7, 30, 'ALTO', 'JUZGADO'),
            $this->etapa('PRESENTA PROYECTO DE ADJUDICACIÓN', 7, 10, 'MEDIO', 'ABOGADO'),
            $this->etapa('TRASLADO DE PROYECTO DE ADJUDICACIÓN E INVENTARIO DE BIENES', 7, 5, 'MEDIO', 'JUZGADO'),
            $this->etapa('APRUEBA ACUERDO DE ADJUDICACIÓN', 7, 10, 'MEDIO', 'JUZGADO'),
            $this->etapa('ACUERDO DE ADJUDICACIÓN EN FIRME', 7, 3, 'BAJO', 'SISTEMA'),
            $this->etapa('ADMITE PROCESO DE REORGANIZACIÓN EMPRESARIAL', 7, 10, 'ALTO', 'JUZGADO'),
            $this->etapa('PRESENTA ACUERDO DE REORGANIZACIÓN', 7, 10, 'MEDIO', 'ABOGADO'),
            $this->etapa('AUDIENCIA DE CONFIRMACIÓN ACUERDO DE REORGANIZACIÓN', 7, 30, 'ALTO', 'JUZGADO'),
            $this->etapa('APRUEBA ACUERDO DE REORGANIZACIÓN', 7, 10, 'MEDIO', 'JUZGADO'),
            $this->etapa('ACUERDO DE REORGANIZACIÓN EN FIRME', 7, 3, 'BAJO', 'SISTEMA'),
            $this->etapa('DENUNCIA INCUMPLIMIENTO DE ACUERDO DE REORGANIZACIÓN', 7, 5, 'MUY_ALTO', 'ABOGADO'),
            $this->etapa('AUDIENCIA DE INCUMPLIMIENTO DEL ACUERDO DE REORGANIZACIÓN', 7, 30, 'MUY_ALTO', 'JUZGADO'),

            // FASE 8: TERMINACIÓN Y ARCHIVO
            $this->etapa('TERMINADO POR PAGO TOTAL', 8, 0, 'BAJO', 'SISTEMA'),
            $this->etapa('TERMINADO POR PAGO MORA', 8, 0, 'BAJO', 'SISTEMA'),
            $this->etapa('TERMINADO POR TRANSACCIÓN / CONCILIACIÓN', 8, 0, 'BAJO', 'ABOGADO'),
            $this->etapa('TERMINADO POR TRANSACCIÓN', 8, 0, 'BAJO', 'ABOGADO'),
            $this->etapa('TERMINADO POR CONCILIACIÓN', 8, 0, 'BAJO', 'ABOGADO'),
            $this->etapa('TERMINADO POR NOVACIÓN', 8, 0, 'BAJO', 'ABOGADO'),
            $this->etapa('TERMINADO POR REESTRUCTURACIÓN', 8, 0, 'BAJO', 'ABOGADO'),
            $this->etapa('TERMINADO POR DESISTIMIENTO TÁCITO (ART. 317 CGP)', 8, 0, 'MUY_ALTO', 'ABOGADO'),
            $this->etapa('TERMINADO POR DESISTIMIENTO TÁCITO', 8, 0, 'MUY_ALTO', 'ABOGADO'),
            $this->etapa('TERMINADO POR PRESCRIPCIÓN', 8, 0, 'MUY_ALTO', 'ABOGADO'),
            $this->etapa('TERMINADO POR PERENCIÓN', 8, 0, 'ALTO', 'ABOGADO'),
            $this->etapa('TERMINADO POR VENTA DE CARTERA', 8, 0, 'BAJO', 'SISTEMA'),
            $this->etapa('TERMINADO POR RENUNCIA PODER', 8, 0, 'MEDIO', 'ABOGADO'),
            $this->etapa('TERMINADO POR ADJUDICACIÓN DE BIENES', 8, 0, 'BAJO', 'SISTEMA'),
            $this->etapa('SOLICITUD DE SUSPENSIÓN DEL PROCESO', 8, 5, 'MEDIO', 'ABOGADO'),
            $this->etapa('SUSPENDIDO', 8, 0, 'MEDIO', 'JUZGADO'),
            $this->etapa('SUSPENDIDO POR TRÁMITE DE INSOLVENCIA', 8, 0, 'ALTO', 'JUZGADO'),
            $this->etapa('ARCHIVO DEFINITIVO', 8, 0, 'BAJO', 'JUZGADO'),
        ];
    }

    private function etapasDesdeCsv(): array
    {
        $csvPath = database_path('seeders/data/juzgados.csv');

        if (! file_exists($csvPath)) {
            $this->command?->warn("No se encontró el CSV de juzgados en {$csvPath}; se usará solo el catálogo base.");

            return [];
        }

        $file = fopen($csvPath, 'r');

        if ($file === false) {
            $this->command?->warn("No fue posible abrir el CSV de juzgados en {$csvPath}; se usará solo el catálogo base.");

            return [];
        }

        $firstLine = fgets($file) ?: '';
        rewind($file);
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        fgetcsv($file, 0, $delimiter);

        $etapas = [];

        while (($row = fgetcsv($file, 0, $delimiter)) !== false) {
            $nombre = $row[self::CSV_ETAPA_COLUMN] ?? null;

            if (is_string($nombre) && trim($nombre) !== '') {
                $etapas[] = [
                    'nombre' => $nombre,
                    'descripcion' => 'Detectada automáticamente en database/seeders/data/juzgados.csv.',
                ];
            }
        }

        fclose($file);

        return $etapas;
    }

    private function consolidarEtapas(array $etapas): array
    {
        $consolidadas = [];

        foreach ($etapas as $etapa) {
            $nombre = $this->normalizarNombre($etapa['nombre'] ?? '');

            if ($nombre === '') {
                continue;
            }

            if (! isset($consolidadas[$nombre])) {
                $etapa['nombre'] = $nombre;
                $consolidadas[$nombre] = $etapa;
            }
        }

        return array_values($consolidadas);
    }

    private function etapa(
        string $nombre,
        int $moduloId,
        int $slaDias,
        string $riesgo,
        string $responsable,
        ?string $descripcion = null,
    ): array {
        return array_filter([
            'nombre' => $nombre,
            'modulo_id' => $moduloId,
            'sla_dias' => $slaDias,
            'riesgo' => $riesgo,
            'responsable' => $responsable,
            'descripcion' => $descripcion,
        ], static fn ($value) => $value !== null);
    }

    private function normalizarNombre(string $nombre): string
    {
        $nombre = preg_replace('/\x{FEFF}/u', '', $nombre) ?? $nombre;
        $nombre = preg_replace('/\s+/u', ' ', trim($nombre)) ?? trim($nombre);

        return mb_strtoupper($nombre, 'UTF-8');
    }

    private function metadataPara(string $nombre): array
    {
        $moduloId = $this->moduloPara($nombre);

        return [
            'modulo_id' => $moduloId,
            'sla_dias' => $this->slaPara($nombre, $moduloId),
            'riesgo' => $this->riesgoPara($nombre),
            'responsable' => $this->responsablePara($nombre),
            'descripcion' => 'Etapa consolidada automáticamente por el seeder de etapas procesales.',
        ];
    }

    private function moduloPara(string $nombre): int
    {
        if ($this->contiene($nombre, ['TERMINA', 'TERMINADO', 'ARCHIVO', 'RETIRADA', 'DEVOLUCION', 'DEVOLUCIÓN', 'DESISTIMIENTO', 'PRESCRIPCION', 'PRESCRIPCIÓN', 'PERENCION', 'PERENCIÓN', 'SUSPENDIDO', 'ORDENA CESAR'])) {
            return 8;
        }

        if ($this->contiene($nombre, ['RECEPCION', 'RECEPCIÓN', 'INVESTIGACION', 'INVESTIGACIÓN', 'VIABILIDAD', 'PREAVISO', 'CARTA PRE', 'ASIGNACION SIN DOCUMENTOS'])) {
            return 1;
        }

        if ($this->contiene($nombre, ['CONCILIACION', 'CONCILIACIÓN', 'ACUERDO', 'NEGOCIACION', 'NEGOCIACIÓN', 'INSOLVENCIA', 'REORGANIZACION', 'REORGANIZACIÓN', 'LIQUIDACION PATRIMONIAL', 'LIQUIDACIÓN PATRIMONIAL', 'ACREENCIAS', 'GRADUACION', 'GRADUACIÓN'])) {
            return 7;
        }

        if ($this->contiene($nombre, ['DEMANDA', 'ADMITE', 'INADMITE', 'RECHAZA', 'RECHAZADA', 'CALIFICACION', 'CALIFICACIÓN', 'SUBSANACION', 'SUBSANACIÓN', 'REPARTO', 'POR PRESENTAR', 'AVOCA', 'CUMPLE REQUISITOS', 'NO CUMPLE REQUISITOS'])) {
            return 2;
        }

        if ($this->contiene($nombre, ['NOTIFICACION', 'NOTIFICACIÓN', 'TRASLADO', 'EXCEPCIONES', 'CONTESTACION', 'CONTESTACIÓN', 'RECONVENCION', 'RECONVENCIÓN', 'EMPLAZAMIENTO', 'CURADOR'])) {
            return 3;
        }

        if ($this->contiene($nombre, ['AUDIENCIA', 'PRUEBA', 'PRUEBAS', 'INSPECCION', 'INSPECCIÓN', 'ALEGATOS'])) {
            return 4;
        }

        if ($this->contiene($nombre, ['SENTENCIA', 'RECURSO', 'APELACION', 'APELACIÓN', 'REPOSICION', 'REPOSICIÓN', 'PROVIDENCIA', 'EJECUTORIADA', 'INTERPONE RECURSO', 'RESUELVE RECURSO'])) {
            return 5;
        }

        if ($this->contiene($nombre, ['MANDAMIENTO', 'MEDIDAS CAUTELARES', 'EMBARGO', 'SECUESTRO', 'LIQUIDACION', 'LIQUIDACIÓN', 'AVALUO', 'AVALÚO', 'REMATE', 'ADJUDICACION', 'ADJUDICACIÓN', 'APREHENSION', 'APREHENSIÓN', 'CAPTURA', 'VEHICULO', 'VEHÍCULO', 'BIEN', 'PAGO DIRECTO', 'TRANSFERENCIA DE DOMINIO'])) {
            return 6;
        }

        return 2;
    }

    private function slaPara(string $nombre, int $moduloId): int
    {
        if ($moduloId === 8) {
            return 0;
        }

        if ($this->contiene($nombre, ['AUDIENCIA', 'REMATE'])) {
            return 30;
        }

        if ($this->contiene($nombre, ['RECURSO', 'SUBSANACION', 'SUBSANACIÓN', 'OBJECIONES', 'EXCEPCIONES', 'NOTIFICACION', 'NOTIFICACIÓN', 'TRASLADO'])) {
            return 5;
        }

        if ($this->contiene($nombre, ['SENTENCIA', 'CONSTANCIA', 'ACTA', 'EN FIRME', 'CLIENTE NOTIFICADO'])) {
            return 1;
        }

        if ($moduloId === 7) {
            return 15;
        }

        if ($moduloId === 6) {
            return 10;
        }

        return 5;
    }

    private function riesgoPara(string $nombre): string
    {
        if ($this->contiene($nombre, ['DESISTIMIENTO TACITO', 'DESISTIMIENTO TÁCITO', 'PRESCRIPCION', 'PRESCRIPCIÓN', 'INCUMPLIMIENTO', 'NO CUMPLE'])) {
            return 'MUY_ALTO';
        }

        if ($this->contiene($nombre, ['RECHAZ', 'NIEGA', 'INADMITE', 'RECURSO', 'SENTENCIA', 'EXCEPCIONES', 'OBJECIONES', 'AUDIENCIA', 'REMATE', 'APREHENSION', 'APREHENSIÓN', 'CAPTURA'])) {
            return 'ALTO';
        }

        if ($this->contiene($nombre, ['TRASLADO', 'NOTIFICACION', 'NOTIFICACIÓN', 'MEDIDAS', 'EMBARGO', 'SECUESTRO', 'LIQUIDACION', 'LIQUIDACIÓN', 'SUSPENDIDO'])) {
            return 'MEDIO';
        }

        return 'BAJO';
    }

    private function responsablePara(string $nombre): string
    {
        if ($this->contiene($nombre, ['CLIENTE', 'DOCUMENTOS'])) {
            return 'CLIENTE';
        }

        if ($this->contiene($nombre, ['ADMITE', 'INADMITE', 'RECHAZ', 'AUDIENCIA', 'SENTENCIA', 'AUTO', 'MANDAMIENTO', 'ORDENA', 'ORDENAN', 'DECRETA', 'APRUEBA', 'CONFIRMA', 'RESUELVE', 'AVOCA', 'NIEGA', 'EN FIRME'])) {
            return 'JUZGADO';
        }

        if ($this->contiene($nombre, ['CONTESTACION', 'CONTESTACIÓN', 'EXCEPCIONES', 'OBJECIONES', 'NO PROPONE', 'PROPONE'])) {
            return 'CONTRAPARTE';
        }

        if ($this->contiene($nombre, ['TERMINADO POR PAGO TOTAL', 'TERMINADO POR PAGO MORA', 'ARCHIVO', 'EN FIRME', 'VENTA DE CARTERA'])) {
            return 'SISTEMA';
        }

        return 'ABOGADO';
    }

    private function contiene(string $nombre, array $palabras): bool
    {
        return Str::contains($nombre, $palabras, true);
    }
}
