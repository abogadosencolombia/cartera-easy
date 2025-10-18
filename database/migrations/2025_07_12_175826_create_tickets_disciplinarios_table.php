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
        Schema::create('tickets_disciplinarios', function (Blueprint $table) {
            $table->id();
            // Cada ticket pertenece a un incidente. Si se borra el incidente, se borra el ticket.
            $table->foreignId('incidente_id')->constrained('incidentes_juridicos')->onDelete('cascade');
            // Quién creó el ticket (un admin, un jefe de comité).
            $table->foreignId('creado_por')->constrained('users');
            // A quién se le asigna la tarea de revisar este ticket.
            $table->foreignId('asignado_a')->nullable()->constrained('users');
            // Las etapas del proceso interno de revisión.
            $table->enum('etapa', ['nuevo', 'en_revision', 'resolucion', 'cerrado'])->default('nuevo');
            $table->text('comentarios')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets_disciplinarios');
    }
};