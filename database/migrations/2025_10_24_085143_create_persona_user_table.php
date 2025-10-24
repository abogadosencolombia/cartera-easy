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
        Schema::create('persona_user', function (Blueprint $table) {
            $table->id();

            // Columna para la Persona
            $table->foreignId('persona_id')->constrained()->onDelete('cascade');
            
            // Columna para el Abogado (que es un User)
            // Le decimos que 'abogado_id' apunta a la columna 'id' en la tabla 'users'
            $table->foreignId('abogado_id')->constrained('users')->onDelete('cascade');

            $table->timestamps();

            // Opcional: Para asegurar que no se pueda asignar el mismo abogado
            // a la misma persona dos veces.
            $table->unique(['persona_id', 'abogado_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('persona_user');
    }
};
