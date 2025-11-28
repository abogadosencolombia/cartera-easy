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
            // Añadimos la columna para el Nivel 4: Subproceso
            // La hacemos 'nullable' para que los casos antiguos no fallen.
            // La ponemos después de 'subtipo_proceso' para mantener el orden.
            $table->string('subproceso')->nullable()->after('subtipo_proceso');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('casos', function (Blueprint $table) {
             // Si existe la columna, la eliminamos
             if (Schema::hasColumn('casos', 'subproceso')) {
                $table->dropColumn('subproceso');
            }
        });
    }
};
