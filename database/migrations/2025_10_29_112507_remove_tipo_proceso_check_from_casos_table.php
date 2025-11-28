<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // <-- ¡Importante añadir esto!

return new class extends Migration
{
    /**
     * Run the migrations.
     * Ejecuta el comando para eliminar la restricción CHECK.
     */
    public function up(): void
    {
        // El nombre 'casos_tipo_proceso_check' viene EXACTAMENTE del error que te dio.
        // DB::statement ejecuta un comando SQL directamente en PostgreSQL.
        // 'IF EXISTS' hace que no falle si por alguna razón ya no existiera.
        DB::statement('ALTER TABLE casos DROP CONSTRAINT IF EXISTS casos_tipo_proceso_check');
    }

    /**
     * Reverse the migrations.
     * Si haces rollback, esto se ejecutaría.
     * Como no sabemos la lista antigua exacta, lo dejamos vacío
     * para simplificar. El rollback simplemente no hará nada aquí.
     */
    public function down(): void
    {
        // Opcional: Podrías intentar recrear la restricción si tuvieras la lista vieja,
        // pero es más seguro dejarlo vacío si no estás seguro.
        // DB::statement('ALTER TABLE casos ADD CONSTRAINT casos_tipo_proceso_check CHECK (tipo_proceso IN (...lista vieja...))');
    }
};