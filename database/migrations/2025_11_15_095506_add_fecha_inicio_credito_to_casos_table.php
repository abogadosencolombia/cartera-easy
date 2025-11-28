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
            // Añadimos la nueva columna. La ponemos después de 'tasa_interes_corriente'
            // para mantener el orden lógico.
            $table->date('fecha_inicio_credito')->nullable()->after('tasa_interes_corriente');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            // Esto permite deshacer la migración si algo sale mal
            $table->dropColumn('fecha_inicio_credito');
        });
    }
};