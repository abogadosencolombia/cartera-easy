<?php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "Sincronizando secuencias de PostgreSQL en PRODUCCIÓN...\n";

// Definir los nombres reales de las tablas para sincronizar sus secuencias
$tables = [
    'especialidades' => 'especialidades_id_seq',
    'tipos_proceso' => 'tipos_proceso_id_seq',
    'subtipos_proceso' => 'subtipos_proceso_id_seq',
    'subprocesos' => 'subprocesos_id_seq',
    'etapas_procesales' => 'etapas_procesales_id_seq'
];

foreach ($tables as $table => $seq) {
    if (Schema::hasTable($table)) {
        $max = DB::table($table)->max('id') ?: 0;
        // Postgres: sincroniza la secuencia con el ID máximo
        DB::statement("SELECT setval('$seq', $max)");
        echo "- Tabla '$table' sincronizada al ID: $max\n";
    }
}

echo "Sincronización finalizada.\n";
