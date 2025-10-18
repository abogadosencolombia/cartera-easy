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
        Schema::create('notificaciones_caso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caso_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // Usuario a notificar
            $table->enum('tipo', ['mora', 'vencimiento', 'documento_faltante', 'inactividad', 'alerta_manual']);
            $table->text('mensaje');
            $table->enum('prioridad', ['baja', 'media', 'alta'])->default('media'); // Mejora Opcional #3
            $table->boolean('leido')->default(false);
            $table->timestamp('fecha_envio')->useCurrent();
            $table->timestamp('programado_para')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notificaciones_caso');
    }
};
