<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('validaciones_legales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caso_id')->constrained('casos')->onDelete('cascade');
            
            // En PostgreSQL usamos VARCHAR con CHECK constraint en lugar de ENUM
            $table->string('tipo')->check("tipo IN ('poder_vencido', 'tasa_usura', 'sin_pagare', 'sin_carta_instrucciones', 'sin_certificacion_saldo', 'tipo_proceso_vs_garantia', 'plazo_excedido_sin_demanda', 'documento_faltante_para_radicar', 'documento_requerido')");
            
            $table->string('estado')->default('cumple')->check("estado IN ('cumple', 'incumple', 'no_aplica')");
            
            // --- NUEVOS CAMPOS ---
            $table->string('nivel_riesgo')->default('medio')->check("nivel_riesgo IN ('bajo', 'medio', 'alto')");
            $table->text('accion_correctiva')->nullable();
            $table->timestamp('fecha_cumplimiento')->nullable();
            // --- FIN NUEVOS CAMPOS ---

            $table->text('observacion')->nullable();
            $table->timestamp('ultima_revision')->nullable();
            $table->timestamps();

            $table->unique(['caso_id', 'tipo']);
        });
        
        // Agregar comentarios (PostgreSQL syntax)
        \DB::statement("COMMENT ON COLUMN validaciones_legales.nivel_riesgo IS 'Clasificación del impacto de la falla.'");
        \DB::statement("COMMENT ON COLUMN validaciones_legales.accion_correctiva IS 'Descripción de cómo se solucionó la falla.'");
        \DB::statement("COMMENT ON COLUMN validaciones_legales.fecha_cumplimiento IS 'Fecha en que se solucionó la falla.'");
    }

    public function down(): void
    {
        Schema::dropIfExists('validaciones_legales');
    }
};