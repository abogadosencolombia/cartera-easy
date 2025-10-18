<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        // En PostgreSQL necesitamos actualizar el CHECK constraint
        // Primero eliminamos el constraint existente
        DB::statement('ALTER TABLE validaciones_legales DROP CONSTRAINT IF EXISTS validaciones_legales_tipo_check');
        
        // Luego agregamos el nuevo constraint con el valor adicional
        DB::statement("ALTER TABLE validaciones_legales ADD CONSTRAINT validaciones_legales_tipo_check CHECK (tipo IN ('poder_vencido', 'tasa_usura', 'sin_pagare', 'sin_carta_instrucciones', 'sin_certificacion_saldo', 'tipo_proceso_vs_garantia', 'plazo_excedido_sin_demanda', 'documento_faltante_para_radicar', 'documento_requerido'))");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        // Revertir: eliminar el constraint y recrear sin 'documento_requerido'
        DB::statement('ALTER TABLE validaciones_legales DROP CONSTRAINT IF EXISTS validaciones_legales_tipo_check');
        
        DB::statement("ALTER TABLE validaciones_legales ADD CONSTRAINT validaciones_legales_tipo_check CHECK (tipo IN ('poder_vencido', 'tasa_usura', 'sin_pagare', 'sin_carta_instrucciones', 'sin_certificacion_saldo', 'tipo_proceso_vs_garantia', 'plazo_excedido_sin_demanda', 'documento_faltante_para_radicar'))");
    }
};