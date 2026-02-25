<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            $table->timestamp('fecha_limite')->nullable()->after('descripcion');
            // Esta bandera sirve para saber si ya le chismeamos al admin que esta tarea se venció
            $table->boolean('aviso_vencimiento_enviado')->default(false)->after('fecha_limite');
        });
    }

    public function down(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            $table->dropColumn(['fecha_limite', 'aviso_vencimiento_enviado']);
        });
    }
};