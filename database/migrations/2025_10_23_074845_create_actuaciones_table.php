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
        Schema::create('actuaciones', function (Blueprint $table) {
            $table->id();
            // Campos para la relación polimórfica (para asociar con Caso, Radicado, Contrato)
            $table->string('actuable_type'); // Guardará el nombre de la clase o un identificador del tipo (ej: 'App\Models\Caso' o simplemente 'Caso')
            $table->unsignedBigInteger('actuable_id'); // Guardará el ID del Caso, Radicado o Contrato

            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null'); // Usuario que CREÓ la actuación
            $table->text('nota'); // La nota o descripción de la actuación
            $table->timestamps(); // created_at (fecha de la actuación) y updated_at

            // Campos para la verificación
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->onDelete('set null'); // Usuario que VERIFICÓ
            $table->timestamp('verified_at')->nullable(); // Fecha y hora de la verificación

            // Índice para optimizar búsquedas por el item asociado
            $table->index(['actuable_type', 'actuable_id']);
            // Índice para optimizar búsquedas por estado de verificación
            $table->index('verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('actuaciones');
    }
};


