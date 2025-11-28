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
        Schema::create('subprocesos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            
            // Llave foránea que lo conecta con el Nivel 3 (SubtipoProceso)
            $table->foreignId('subtipo_proceso_id')
                  ->constrained('subtipos_proceso') // <-- ¡CORREGIDO! Ahora coincide con tu Model.
                  ->onDelete('cascade');
            
            $table->timestamps();

            // Índice único para evitar duplicados
            $table->unique(['subtipo_proceso_id', 'nombre']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subprocesos');
    }
};

