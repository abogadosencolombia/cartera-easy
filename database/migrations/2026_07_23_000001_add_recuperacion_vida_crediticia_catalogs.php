<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TIPO_PROCESO = 'RECUPERACIÓN DE VIDA CREDITICIA';

    private const ETAPAS = [
        'PETICIÓN' => [
            'modulo_id' => 1,
            'sla_dias' => 15,
            'riesgo' => 'MEDIO',
            'responsable' => 'ABOGADO',
            'descripcion' => 'Presentación y seguimiento de la petición para la recuperación de la vida crediticia.',
        ],
        'ACCIÓN DE TUTELA' => [
            'modulo_id' => 2,
            'sla_dias' => 1,
            'riesgo' => 'ALTO',
            'responsable' => 'ABOGADO',
            'descripcion' => 'Preparación y presentación de la acción de tutela.',
        ],
        'IMPUGNACIÓN' => [
            'modulo_id' => 5,
            'sla_dias' => 3,
            'riesgo' => 'ALTO',
            'responsable' => 'ABOGADO',
            'descripcion' => 'Impugnación del fallo de tutela de primera instancia.',
        ],
        'SEGUNDA INSTANCIA' => [
            'modulo_id' => 5,
            'sla_dias' => 20,
            'riesgo' => 'ALTO',
            'responsable' => 'JUZGADO',
            'descripcion' => 'Trámite y decisión de la tutela en segunda instancia.',
        ],
    ];

    public function up(): void
    {
        $this->agregarTipoProceso();
        $this->agregarEtapas();
    }

    public function down(): void
    {
        $this->eliminarEtapasSinUso();
        $this->eliminarTipoProcesoSinUso();
    }

    private function agregarTipoProceso(): void
    {
        if (! Schema::hasTable('tipos_proceso')) {
            return;
        }

        DB::table('tipos_proceso')->updateOrInsert(
            ['nombre' => self::TIPO_PROCESO],
            [
                'descripcion' => 'Gestión de peticiones y acciones constitucionales para recuperar la vida crediticia.',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function agregarEtapas(): void
    {
        if (! Schema::hasTable('etapas_procesales')) {
            return;
        }

        $orden = (int) DB::table('etapas_procesales')->max('orden');

        foreach (self::ETAPAS as $nombre => $metadata) {
            $existente = DB::table('etapas_procesales')->where('nombre', $nombre)->first();
            $valores = $this->columnasEtapaDisponibles(array_merge($metadata, [
                'updated_at' => now(),
            ]));

            if ($existente) {
                DB::table('etapas_procesales')->where('id', $existente->id)->update($valores);

                continue;
            }

            $orden++;
            DB::table('etapas_procesales')->insert(array_merge(
                ['nombre' => $nombre, 'orden' => $orden, 'created_at' => now()],
                $valores,
            ));
        }
    }

    private function columnasEtapaDisponibles(array $valores): array
    {
        return collect($valores)
            ->filter(fn (mixed $valor, string $columna) => Schema::hasColumn('etapas_procesales', $columna))
            ->all();
    }

    private function eliminarEtapasSinUso(): void
    {
        if (! Schema::hasTable('etapas_procesales')) {
            return;
        }

        foreach (array_keys(self::ETAPAS) as $nombre) {
            $etapa = DB::table('etapas_procesales')->where('nombre', $nombre)->first();

            if (! $etapa || $this->etapaTieneReferencias((int) $etapa->id)) {
                continue;
            }

            DB::table('etapas_procesales')->where('id', $etapa->id)->delete();
        }
    }

    private function etapaTieneReferencias(int $etapaId): bool
    {
        foreach ([
            ['proceso_radicados', 'etapa_procesal_id'],
            ['proceso_radicados', 'etapa_actual_id'],
            ['casos', 'etapa_procesal_id'],
        ] as [$tabla, $columna]) {
            if (
                Schema::hasTable($tabla)
                && Schema::hasColumn($tabla, $columna)
                && DB::table($tabla)->where($columna, $etapaId)->exists()
            ) {
                return true;
            }
        }

        return false;
    }

    private function eliminarTipoProcesoSinUso(): void
    {
        if (! Schema::hasTable('tipos_proceso')) {
            return;
        }

        $tipo = DB::table('tipos_proceso')->where('nombre', self::TIPO_PROCESO)->first();

        if (! $tipo) {
            return;
        }

        foreach (['proceso_radicados', 'requisitos_documento', 'subtipos_proceso', 'etapas_proceso'] as $tabla) {
            if (
                Schema::hasTable($tabla)
                && Schema::hasColumn($tabla, 'tipo_proceso_id')
                && DB::table($tabla)->where('tipo_proceso_id', $tipo->id)->exists()
            ) {
                return;
            }
        }

        DB::table('tipos_proceso')->where('id', $tipo->id)->delete();
    }
};
