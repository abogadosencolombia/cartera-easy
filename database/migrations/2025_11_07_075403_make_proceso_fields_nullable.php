<?php

// --- CORREGIDO ---
// El error estaba en esta línea, decía "Illuminateate"
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Este archivo hace que los campos que ya no son obligatorios
     * acepten valores NULL en la base de datos.
     */
    public function up(): void
    {
        Schema::table('proceso_radicados', function (Blueprint $table) {
            // Campos de texto y fecha que ahora son opcionales
            $table->string('radicado')->nullable()->change();
            $table->date('fecha_radicado')->nullable()->change();
            $table->string('naturaleza', 255)->nullable()->change();
            $table->text('asunto')->nullable()->change(); // Usamos text() por si 'asunto' es largo
            $table->string('correo_radicacion', 255)->nullable()->change();
            $table->date('fecha_revision')->nullable()->change();
            $table->text('ultima_actuacion')->nullable()->change();
            $table->string('link_expediente', 1024)->nullable()->change();
            $table->string('ubicacion_drive', 1024)->nullable()->change();
            $table->text('correos_juzgado')->nullable()->change();
            $table->text('observaciones')->nullable()->change();
            
            // Relaciones que ahora son opcionales
            $table->foreignId('responsable_revision_id')->nullable()->change();
            $table->foreignId('juzgado_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // En caso de rollback, podríamos revertir los cambios
        // pero por simplicidad y seguridad, no lo haremos automáticamente.
        Schema::table('proceso_radicados', function (Blueprint $table) {
            // Revertir esto requeriría poner un valor por defecto
            // en todas las filas que ahora son nulas.
        });
    }
};