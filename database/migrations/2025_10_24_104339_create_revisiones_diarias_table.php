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
        Schema::create('revisiones_diarias', function (Blueprint $table) {
            $table->id();
            
            // Quién realizó la revisión
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Qué día se revisó (solo la fecha)
            $table->date('fecha_revision');
            
            // El item polimórfico (Caso, Radicado o Contrato)
            $table->morphs('revisable'); // Esto crea revisable_id (unsignedBigInt) y revisable_type (string)

            $table->timestamps();

            // Un usuario solo puede revisar un item específico una vez por día.
            $table->unique(
                ['user_id', 'fecha_revision', 'revisable_id', 'revisable_type'],
                'user_fecha_revisable_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revisiones_diarias');
    }
};
