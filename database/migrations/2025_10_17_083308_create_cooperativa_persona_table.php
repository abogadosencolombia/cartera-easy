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
        Schema::create('cooperativa_persona', function (Blueprint $table) {
            $table->id();

            // Define las llaves foráneas y las enlaza a tus tablas existentes.
            $table->foreignId('persona_id')->constrained('personas')->onDelete('cascade');
            $table->foreignId('cooperativa_id')->constrained('cooperativas')->onDelete('cascade');

            // Columnas extra para describir la relación (pivote).
            $table->string('cargo')->nullable();
            $table->string('status')->default('activo');

            $table->timestamps();

            // Opcional pero recomendado: Evita que una persona se asocie dos veces
            // a la misma cooperativa.
            $table->unique(['persona_id', 'cooperativa_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cooperativa_persona');
    }
};