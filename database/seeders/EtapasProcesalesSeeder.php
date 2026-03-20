<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EtapaProcesal;

class EtapasProcesalesSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Actualizando etapas procesales incrementalmente (SIN BORRADO)...');

        $etapas = [
            // FASE 1: POSTULACIÓN (Módulo 2)
            ['nombre' => 'ELABORACIÓN DE DEMANDA', 'modulo_id' => 2, 'sla_dias' => 5, 'riesgo' => 'BAJO', 'responsable' => 'ABOGADO'],
            ['nombre' => 'DEMANDA PRESENTADA', 'modulo_id' => 2, 'sla_dias' => 1, 'riesgo' => 'BAJO', 'responsable' => 'SISTEMA'],
            ['nombre' => 'REPARTO / ASIGNACIÓN JUZGADO', 'modulo_id' => 2, 'sla_dias' => 3, 'riesgo' => 'BAJO', 'responsable' => 'JUZGADO'],
            ['nombre' => 'CALIFICACIÓN: ADMITIDA', 'modulo_id' => 2, 'sla_dias' => 10, 'riesgo' => 'BAJO', 'responsable' => 'JUZGADO'],
            ['nombre' => 'CALIFICACIÓN: INADMITIDA', 'modulo_id' => 2, 'sla_dias' => 5, 'riesgo' => 'MEDIO', 'responsable' => 'ABOGADO'],
            ['nombre' => 'CALIFICACIÓN: RECHAZADA', 'modulo_id' => 2, 'sla_dias' => 3, 'riesgo' => 'ALTO', 'responsable' => 'ABOGADO'],
            ['nombre' => 'SUBSANACIÓN DE DEMANDA', 'modulo_id' => 2, 'sla_dias' => 5, 'riesgo' => 'MEDIO', 'responsable' => 'ABOGADO'],

            // FASE 2: NOTIFICACIÓN (Módulo 3)
            ['nombre' => 'NOTIFICACIÓN LEY 2213 (MENSAJE DE DATOS)', 'modulo_id' => 3, 'sla_dias' => 2, 'riesgo' => 'BAJO', 'responsable' => 'ABOGADO'],
            ['nombre' => 'NOTIFICACIÓN PERSONAL (ART. 291 CGP)', 'modulo_id' => 3, 'sla_dias' => 5, 'riesgo' => 'BAJO', 'responsable' => 'ABOGADO'],
            ['nombre' => 'NOTIFICACIÓN POR AVISO (ART. 292 CGP)', 'modulo_id' => 3, 'sla_dias' => 5, 'riesgo' => 'BAJO', 'responsable' => 'ABOGADO'],
            ['nombre' => 'EMPLAZAMIENTO', 'modulo_id' => 3, 'sla_dias' => 15, 'riesgo' => 'MEDIO', 'responsable' => 'ABOGADO'],
            ['nombre' => 'NOMBRAMIENTO CURADOR AD LITEM', 'modulo_id' => 3, 'sla_dias' => 10, 'riesgo' => 'MEDIO', 'responsable' => 'JUZGADO'],

            // FASE 3: CONTRADICCIÓN Y DEFENSA (Módulo 3)
            ['nombre' => 'TÉRMINO DE TRASLADO CORRIENDO', 'modulo_id' => 3, 'sla_dias' => 20, 'riesgo' => 'BAJO', 'responsable' => 'CONTRAPARTE'],
            ['nombre' => 'CONTESTACIÓN DE DEMANDA', 'modulo_id' => 3, 'sla_dias' => 1, 'riesgo' => 'MEDIO', 'responsable' => 'CONTRAPARTE'],
            ['nombre' => 'EXCEPCIONES DE MÉRITO / PREVIAS', 'modulo_id' => 3, 'sla_dias' => 10, 'riesgo' => 'ALTO', 'responsable' => 'CONTRAPARTE'],
            ['nombre' => 'DEMANDA DE RECONVENCIÓN', 'modulo_id' => 3, 'sla_dias' => 20, 'riesgo' => 'ALTO', 'responsable' => 'CONTRAPARTE'],

            // FASE 4: AUDIENCIAS (Módulo 4)
            ['nombre' => 'AUDIENCIA INICIAL (ART. 372 CGP)', 'modulo_id' => 4, 'sla_dias' => 30, 'riesgo' => 'ALTO', 'responsable' => 'JUZGADO'],
            ['nombre' => 'AUDIENCIA INSTRUCCIÓN Y JUZGAMIENTO (ART. 373 CGP)', 'modulo_id' => 4, 'sla_dias' => 60, 'riesgo' => 'MUY_ALTO', 'responsable' => 'JUZGADO'],
            ['nombre' => 'AUDIENCIA ÚNICA (VERBAL SUMARIO)', 'modulo_id' => 4, 'sla_dias' => 30, 'riesgo' => 'MUY_ALTO', 'responsable' => 'JUZGADO'],

            // FASE 5: SENTENCIA Y RECURSOS (Módulo 5)
            ['nombre' => 'SENTENCIA DE PRIMERA INSTANCIA', 'modulo_id' => 5, 'sla_dias' => 1, 'riesgo' => 'MUY_ALTO', 'responsable' => 'JUZGADO'],
            ['nombre' => 'RECURSO DE APELACIÓN / REPOSICIÓN', 'modulo_id' => 5, 'sla_dias' => 5, 'riesgo' => 'ALTO', 'responsable' => 'ABOGADO'],
            ['nombre' => 'SENTENCIA EN FIRME / EJECUTORIADA', 'modulo_id' => 5, 'sla_dias' => 3, 'riesgo' => 'BAJO', 'responsable' => 'SISTEMA'],

            // FASE 6: EJECUCIÓN (Módulo 6)
            ['nombre' => 'MANDAMIENTO DE PAGO', 'modulo_id' => 6, 'sla_dias' => 10, 'riesgo' => 'BAJO', 'responsable' => 'JUZGADO'],
            ['nombre' => 'MEDIDAS CAUTELARES (EMBARGO/SECUESTRO)', 'modulo_id' => 6, 'sla_dias' => 15, 'riesgo' => 'MEDIO', 'responsable' => 'ABOGADO'],
            ['nombre' => 'LIQUIDACIÓN DE CRÉDITO Y COSTAS', 'modulo_id' => 6, 'sla_dias' => 10, 'riesgo' => 'MEDIO', 'responsable' => 'ABOGADO'],
            ['nombre' => 'AVALÚO DE BIENES', 'modulo_id' => 6, 'sla_dias' => 20, 'riesgo' => 'MEDIO', 'responsable' => 'ABOGADO'],
            ['nombre' => 'REMATE DE BIENES', 'modulo_id' => 6, 'sla_dias' => 60, 'riesgo' => 'ALTO', 'responsable' => 'JUZGADO'],

            // FASE 7: TERMINACIÓN Y ARCHIVO (Módulo 8)
            ['nombre' => 'TERMINADO POR PAGO TOTAL', 'modulo_id' => 8, 'sla_dias' => 0, 'riesgo' => 'BAJO', 'responsable' => 'SISTEMA'],
            ['nombre' => 'TERMINADO POR TRANSACCIÓN / CONCILIACIÓN', 'modulo_id' => 8, 'sla_dias' => 0, 'riesgo' => 'BAJO', 'responsable' => 'ABOGADO'],
            ['nombre' => 'TERMINADO POR DESISTIMIENTO TÁCITO (ART. 317 CGP)', 'modulo_id' => 8, 'sla_dias' => 0, 'riesgo' => 'MUY_ALTO', 'responsable' => 'ABOGADO'],
            ['nombre' => 'ARCHIVO DEFINITIVO', 'modulo_id' => 8, 'sla_dias' => 0, 'riesgo' => 'BAJO', 'responsable' => 'JUZGADO'],
        ];

        foreach ($etapas as $index => $data) {
            EtapaProcesal::updateOrCreate(
                ['nombre' => $data['nombre']],
                [
                    'modulo_id' => $data['modulo_id'],
                    'sla_dias'  => $data['sla_dias'],
                    'riesgo'    => $data['riesgo'],
                    'responsable' => $data['responsable'],
                    'orden'     => ($index + 1) * 10 // Multiplicamos por 10 para permitir inserciones intermedias en el futuro
                ]
            );
        }

        $this->command->info('Se han actualizado ' . count($etapas) . ' etapas procesales correctamente.');
    }
}
