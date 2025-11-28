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
        Schema::table('documentos_caso', function (Blueprint $table) {
            // Añade la nota (nullable para documentos antiguos)
            // La coloco después de 'ruta_archivo' para orden lógico.
            $table->text('nota')->nullable()->after('ruta_archivo');

            // Añade la FK para el codeudor (nullable)
            // Asumo que los codeudores están en la tabla 'personas'
            // La coloco después de 'caso_id'.
            $table->foreignId('codeudor_id')
                  ->nullable()
                  ->after('caso_id')
                  ->constrained('personas') // ¡Verifica que 'personas' sea tu tabla correcta!
                  ->nullOnDelete(); // Si se elimina la persona, este campo se vuelve null.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documentos_caso', function (Blueprint $table) {
            // El orden de eliminación es importante (primero la FK)
            $table->dropForeign(['codeudor_id']);
            $table->dropColumn(['codeudor_id', 'nota']);
        });
    }
};