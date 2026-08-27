<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const STAGES = [
        [
            'nombre' => 'CONFLICTO DE COMPETENCIA',
            'modulo_id' => 2,
            'sla_dias' => 5,
            'riesgo' => 'ALTO',
            'responsable' => 'JUZGADO',
            'descripcion' => 'Definición del despacho competente para continuar el proceso.',
        ],
        [
            'nombre' => 'SOLICITUD DE REFORMA A LA DEMANDA',
            'modulo_id' => 2,
            'sla_dias' => 5,
            'riesgo' => 'MEDIO',
            'responsable' => 'ABOGADO',
            'descripcion' => 'Preparación o presentación de una solicitud de reforma de la demanda.',
        ],
        [
            'nombre' => 'SUBSANACION 2',
            'modulo_id' => 2,
            'sla_dias' => 5,
            'riesgo' => 'MEDIO',
            'responsable' => 'ABOGADO',
            'descripcion' => 'Segunda subsanación requerida dentro de la etapa de admisión.',
        ],
        [
            'nombre' => 'SUSPENDIDO POR ACUERDO DE PAGO',
            'modulo_id' => 8,
            'sla_dias' => 0,
            'riesgo' => 'MEDIO',
            'responsable' => 'JUZGADO',
            'descripcion' => 'Proceso suspendido mientras se cumple un acuerdo de pago.',
        ],
    ];

    public function up(): void
    {
        $nextOrder = ((int) DB::table('etapas_procesales')->max('orden')) + 1;
        $timestamp = now();

        foreach (self::STAGES as $offset => $stage) {
            $query = DB::table('etapas_procesales')->where('nombre', $stage['nombre']);
            $values = [
                ...$stage,
                'orden' => $nextOrder + $offset,
                'updated_at' => $timestamp,
            ];

            if ($query->exists()) {
                $query->update($values);
            } else {
                DB::table('etapas_procesales')->insert([
                    ...$values,
                    'created_at' => $timestamp,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Se preserva el catálogo porque los casos históricos guardan estos
        // nombres como texto y eliminarlos dejaría opciones no seleccionables.
    }
};
