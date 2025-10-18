<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        // Se modifica la tabla para añadir la columna de relación.
        Schema::table('validaciones_legales', function (Blueprint $table) {
            
            // ===== ¡CÓDIGO FINAL Y CORRECTO! =====
            // Usa el nombre de tabla 'requisitos_documento' que encontramos en tu modelo.
            $table->foreignId('requisito_id')
                  ->nullable()
                  ->after('caso_id')
                  ->constrained('requisitos_documento') // <-- ¡Este es el nombre correcto!
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('validaciones_legales', function (Blueprint $table) {
            // Este método revierte los cambios de forma segura.
            $table->dropForeign(['requisito_id']);
            $table->dropColumn('requisito_id');
        });
    }
};
