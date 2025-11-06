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
        Schema::table('casos', function (Blueprint $table) {
            // Hacemos que todas las columnas de la jerarquía acepten nulos
            // Usamos ->change() para modificar las columnas existentes.
            
            // Asumiendo que especialidad_id es un foreignId (unsignedBigInteger)
            // Si usaste ->integer() en la migración original, usa ->integer() aquí también.
            $table->unsignedBigInteger('especialidad_id')->nullable()->change();
            
            $table->string('tipo_proceso')->nullable()->change();
            $table->string('subtipo_proceso')->nullable()->change();
            
            // Esta columna ya la habíamos hecho nullable en una migración anterior,
            // pero la incluimos aquí por seguridad y para unificar la lógica.
            $table->string('subproceso')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            // Advertencia: Revertir esto a "not nullable" puede fallar
            // si ya existen datos nulos en la tabla.
            // $table->unsignedBigInteger('especialidad_id')->nullable(false)->change();
            // $table->string('tipo_proceso')->nullable(false)->change();
            // $table->string('subtipo_proceso')->nullable(false)->change();
            // $table->string('subproceso')->nullable(false)->change();
        });
    }
};
