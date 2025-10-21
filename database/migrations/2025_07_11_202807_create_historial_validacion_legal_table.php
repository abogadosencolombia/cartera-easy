<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historial_validacion_legal', function (Blueprint $table) {
            $table->id();
            // Referencia a la validación que cambió
            $table->foreignId('validacion_legal_id')->constrained('validaciones_legales')->onDelete('cascade');
            
            // Guardamos el estado anterior y el nuevo para tener contexto completo
            $table->enum('estado_anterior', ['cumple', 'incumple', 'no_aplica'])->nullable();
            $table->enum('estado_nuevo', ['cumple', 'incumple', 'no_aplica']);

            // ¿Quién hizo el cambio? Si se borra el usuario, el registro no se pierde.
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            
            $table->text('comentario')->nullable()->comment('Razón del cambio, si se proporciona.');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_validacion_legal');
    }
};