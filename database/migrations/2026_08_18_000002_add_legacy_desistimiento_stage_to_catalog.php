<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const STAGE = 'TERMINADO POR DESISTIMIENTO TACITO';

    public function up(): void
    {
        $query = DB::table('etapas_procesales')->where('nombre', self::STAGE);
        $values = [
            'modulo_id' => 8,
            'sla_dias' => 0,
            'riesgo' => 'MUY_ALTO',
            'responsable' => 'ABOGADO',
            'descripcion' => 'Terminación por desistimiento tácito, conservando la variante histórica sin tilde.',
            'updated_at' => now(),
        ];

        if ($query->exists()) {
            $query->update($values);

            return;
        }

        DB::table('etapas_procesales')->insert([
            'nombre' => self::STAGE,
            'orden' => ((int) DB::table('etapas_procesales')->max('orden')) + 1,
            ...$values,
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Se preserva porque los casos históricos almacenan el nombre como texto.
    }
};
