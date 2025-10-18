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
        Schema::table('users', function (Blueprint $table) {
            // Añadimos la columna para las preferencias, que es de tipo JSON y puede ser nula.
            // La colocamos después de 'estado_activo' por orden lógico.
            $table->json('preferencias_notificacion')->nullable()->after('estado_activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('preferencias_notificacion');
        });
    }
};