<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertas_caso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caso_id')->constrained()->onDelete('cascade');
            // MEJORA IMPLEMENTADA: Enum para estandarizar los tipos de alerta.
            $table->enum('tipo_alerta', ['vencimiento', 'sin_documento', 'inactividad']);
            $table->text('descripcion')->nullable();
            $table->boolean('leida')->default(false); // Cambiamos 'activa' por 'leida' para mejor semántica.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas_caso');
    }
};
