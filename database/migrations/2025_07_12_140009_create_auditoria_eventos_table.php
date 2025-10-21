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
        Schema::create('auditoria_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('evento'); // Ej: 'LOGIN_EXITOSO', 'DOCUMENTO_DESCARGADO'
            $table->string('descripcion_breve'); // Ej: 'El usuario John Doe ha iniciado sesión'
            
            // Campos para relacionar el evento con un objeto específico (polimorfismo)
            $table->nullableMorphs('auditable'); // Esto crea 'auditable_id' y 'auditable_type'

            $table->enum('criticidad', ['baja', 'media', 'alta'])->default('baja');
            $table->text('detalle_anterior')->nullable(); // JSON con el estado ANTES del cambio
            $table->text('detalle_nuevo')->nullable(); // JSON con el estado DESPUÉS del cambio
            $table->ipAddress('direccion_ip')->nullable();
            $table->text('user_agent')->nullable(); // Guarda información del navegador
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auditoria_eventos');
    }
};
