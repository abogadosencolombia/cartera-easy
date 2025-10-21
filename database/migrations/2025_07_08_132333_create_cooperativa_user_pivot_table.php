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
        // Creamos la tabla 'cooperativa_user' que conectará a los usuarios con las cooperativas.
        Schema::create('cooperativa_user', function (Blueprint $table) {
            // No necesitamos un 'id' primario, la combinación de las dos llaves foráneas será única.
            $table->primary(['user_id', 'cooperativa_id']);

            // Llave foránea que apunta a la tabla 'users'.
            // Si un usuario es eliminado, todas sus asignaciones se eliminan en cascada.
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Llave foránea que apunta a la tabla 'cooperativas'.
            // Si una cooperativa es eliminada, todas sus asignaciones se eliminan en cascada.
            $table->foreignId('cooperativa_id')->constrained()->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cooperativa_user');
    }
};
