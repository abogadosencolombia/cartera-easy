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
        Schema::create('tareas', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('descripcion');
            
            // Quién asigna la tarea (Admin)
            $table->foreignId('admin_id')->constrained('users')->onDelete('cascade');
            
            // A quién se le asigna (Abogado/Gestor)
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Relación Polimórfica (para vincular a Caso, Proceso, Contrato, etc.)
            // 'tarea_type' -> App\Models\ProcesoRadicado
            // 'tarea_id'   -> 123
            $table->morphs('tarea'); 

            $table->string('estado')->default('pendiente'); // pendiente, completada
            $table->timestamp('fecha_completado')->nullable();
            $table->timestamps(); // 'created_at' será nuestra 'fecha_asignacion'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tareas');
    }
};