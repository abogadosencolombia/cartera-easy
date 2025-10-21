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
        Schema::create('bitacoras_caso', function (Blueprint $table) {
            $table->id();
            // Relación con el caso al que pertenece esta entrada de la bitácora.
            $table->foreignId('caso_id')->constrained()->onDelete('cascade');
            // Relación con el usuario que realizó la acción.
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // La acción realizada (ej: "Creación de caso", "Llamada realizada", "Documento adjuntado").
            $table->string('accion');
            // Un campo de texto largo para añadir detalles, notas o comentarios sobre la acción.
            $table->text('comentario')->nullable();
            
            $table->timestamps(); // Registra cuándo se creó la entrada en la bitácora.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bitacoras_caso');
    }
};
