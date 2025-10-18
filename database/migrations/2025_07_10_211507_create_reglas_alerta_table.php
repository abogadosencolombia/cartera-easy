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
        Schema::create('reglas_alerta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperativa_id')->constrained()->onDelete('cascade');
            $table->enum('tipo', ['mora', 'vencimiento', 'documento_faltante', 'inactividad']);
            $table->integer('dias'); // Días de espera para generar la alerta
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reglas_alerta');
    }
};
