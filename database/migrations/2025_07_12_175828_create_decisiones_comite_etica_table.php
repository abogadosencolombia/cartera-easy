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
        Schema::create('decisiones_comite_etica', function (Blueprint $table) {
            $table->id();
            // Cada decisión está atada a un ticket específico.
            $table->foreignId('ticket_id')->constrained('tickets_disciplinarios')->onDelete('cascade');
            // Quién del comité tomó la decisión final.
            $table->foreignId('revisado_por')->constrained('users');
            $table->text('resumen_decision');
            // El veredicto del comité.
            $table->enum('resultado', ['sin_falta', 'falta_leve', 'falta_grave', 'sancionado']);
            $table->date('fecha_revision');

            // --- MEJORAS DEL MÓDULO AVANZADO ---
            // Para registrar acciones que no son castigos, como mejorar un proceso.
            $table->text('medida_administrativa')->nullable();
            // Para saber si se mandó al usuario a un curso de refuerzo.
            $table->boolean('requiere_capacitacion')->default(false);
            $table->boolean('caso_reasignado')->default(false);
            // --- FIN DE MEJORAS ---

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('decisiones_comite_etica');
    }
};