<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Paso 1: Eliminar cualquier constraint existente en la columna prioridad
        DB::statement('ALTER TABLE notificaciones_caso DROP CONSTRAINT IF EXISTS notificaciones_caso_prioridad_check');
        
        // Paso 2: Cambiar el tipo de columna a VARCHAR si no lo es ya
        Schema::table('notificaciones_caso', function (Blueprint $table) {
            $table->string('prioridad')->default('media')->change();
        });
        
        // Paso 3: Agregar el nuevo CHECK constraint
        DB::statement("ALTER TABLE notificaciones_caso ADD CONSTRAINT notificaciones_caso_prioridad_check CHECK (prioridad IN ('baja', 'media', 'alta'))");
    }

    public function down(): void
    {
        // Eliminar el constraint
        DB::statement('ALTER TABLE notificaciones_caso DROP CONSTRAINT IF EXISTS notificaciones_caso_prioridad_check');
        
        // Cambiar de vuelta a string simple
        Schema::table('notificaciones_caso', function (Blueprint $table) {
            $table->string('prioridad')->default('media')->change();
        });
    }
};