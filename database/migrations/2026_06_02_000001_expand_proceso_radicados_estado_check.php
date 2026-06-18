<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE proceso_radicados DROP CONSTRAINT IF EXISTS proceso_radicados_estado_check');
        DB::statement("ALTER TABLE proceso_radicados ADD CONSTRAINT proceso_radicados_estado_check CHECK (estado IN ('ACTIVO', 'CERRADO', 'ARCHIVADO', 'FINALIZADO', 'TERMINADO'))");
    }

    public function down(): void
    {
        DB::statement("UPDATE proceso_radicados SET estado = 'CERRADO' WHERE estado NOT IN ('ACTIVO', 'CERRADO')");
        DB::statement('ALTER TABLE proceso_radicados DROP CONSTRAINT IF EXISTS proceso_radicados_estado_check');
        DB::statement("ALTER TABLE proceso_radicados ADD CONSTRAINT proceso_radicados_estado_check CHECK (estado IN ('ACTIVO', 'CERRADO'))");
    }
};
