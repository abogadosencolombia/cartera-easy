<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tipos_proceso')) {
            return;
        }

        $now = now();
        $values = ['updated_at' => $now];

        if (Schema::hasColumn('tipos_proceso', 'especialidad_juridica_id')) {
            $values['especialidad_juridica_id'] = Schema::hasTable('especialidades_juridicas')
                ? DB::table('especialidades_juridicas')->where('nombre', 'CIVIL')->value('id')
                : null;
        }

        $existing = DB::table('tipos_proceso')->where('nombre', 'COMPRAVENTA')->first();

        if ($existing) {
            DB::table('tipos_proceso')->where('id', $existing->id)->update($values);
            return;
        }

        DB::table('tipos_proceso')->insert(array_merge([
            'nombre' => 'COMPRAVENTA',
            'created_at' => $now,
        ], $values));
    }

    public function down(): void
    {
        if (!Schema::hasTable('tipos_proceso')) {
            return;
        }

        $tipo = DB::table('tipos_proceso')->where('nombre', 'COMPRAVENTA')->first();

        if (!$tipo || $this->hasReferences((int) $tipo->id)) {
            return;
        }

        DB::table('tipos_proceso')->where('id', $tipo->id)->delete();
    }

    private function hasReferences(int $tipoProcesoId): bool
    {
        foreach (['proceso_radicados', 'requisitos_documento', 'subtipos_proceso', 'etapas_proceso'] as $table) {
            if (
                Schema::hasTable($table)
                && Schema::hasColumn($table, 'tipo_proceso_id')
                && DB::table($table)->where('tipo_proceso_id', $tipoProcesoId)->exists()
            ) {
                return true;
            }
        }

        return false;
    }
};
