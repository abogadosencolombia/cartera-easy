<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            // Añade la columna para vincular al radicado
            // Usamos ->nullable() y ->nullOnDelete() para que si se borra un radicado,
            // el contrato no se borre, solo pierda el vínculo.
            $table->foreignId('proceso_id')
                  ->nullable()
                  ->after('id') // Opcional: para ponerlo cerca del 'cliente_id'
                  ->constrained('proceso_radicados') // Asume que tu tabla de radicados es 'proceso_radicados'
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            // Esto permite revertir la migración de forma segura
            $table->dropForeign(['proceso_id']);
            $table->dropColumn('proceso_id');
        });
    }
};
