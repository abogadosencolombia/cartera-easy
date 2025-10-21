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
        Schema::create('plantillas_documento', function (Blueprint $table) {
            $table->id();
            
            // --- CORRECCIÓN TÁCTICA ---
            // Añadimos ->nullable() para permitir plantillas globales.
            $table->foreignId('cooperativa_id')->nullable()->constrained()->onDelete('cascade');
            
            $table->string('nombre');
            $table->enum('tipo', ['demanda', 'carta', 'medida cautelar', 'notificación', 'otros']);
            $table->string('archivo');
            $table->string('version')->default('1.0');
            $table->json('aplica_a')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plantillas_documento');
    }
};
