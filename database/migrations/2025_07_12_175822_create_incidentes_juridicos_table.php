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
        Schema::create('incidentes_juridicos', function (Blueprint $table) {
            $table->id();
            // Relación con un caso de otro módulo (ej. auditoría). Nulable por si se crea manualmente.
            $table->foreignId('caso_id')->nullable()->constrained()->onDelete('set null');
            // Quién es el principal implicado en el incidente.
            $table->foreignId('usuario_responsable_id')->nullable()->constrained('users')->onDelete('set null');
            // De dónde vino el reporte: validación legal, auditoría o un admin.
            $table->enum('origen', ['validacion', 'auditoria', 'manual']);
            $table->string('asunto', 255);
            $table->text('descripcion');
            // Estados claros para saber en qué va el caso.
            $table->enum('estado', ['pendiente', 'en_revision', 'resuelto', 'archivado'])->default('pendiente');
            $table->timestamp('fecha_registro')->useCurrent();
            $table->timestamps(); // Esto crea las columnas created_at y updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidentes_juridicos');
    }
};