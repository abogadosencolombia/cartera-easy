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
        Schema::table('actuaciones', function (Blueprint $table) {
            // Añadir la nueva columna de fecha después de 'nota'
            $table->date('fecha_actuacion')->nullable()->after('nota');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('actuaciones', function (Blueprint $table) {
            // Eliminar la columna si se hace rollback
            $table->dropColumn('fecha_actuacion');
        });
    }
};
