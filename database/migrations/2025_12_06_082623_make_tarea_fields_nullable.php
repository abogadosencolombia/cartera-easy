<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            // Permitimos que no haya vinculación (tarea suelta)
            $table->string('tarea_type')->nullable()->change();
            $table->unsignedBigInteger('tarea_id')->nullable()->change();
            
            // Aseguramos que la fecha límite sea opcional (por si no lo era)
            $table->timestamp('fecha_limite')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Revertir cambios es difícil si ya hay datos nulos, 
        // pero por formalidad definimos el down.
        Schema::table('tareas', function (Blueprint $table) {
            $table->string('tarea_type')->nullable(false)->change();
            $table->unsignedBigInteger('tarea_id')->nullable(false)->change();
        });
    }
};