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
        Schema::table('pagos_caso', function (Blueprint $table) {
            // Añade la columna para guardar la ruta del archivo después de 'motivo_pago'
            $table->string('comprobante_path')->nullable()->after('motivo_pago');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pagos_caso', function (Blueprint $table) {
            // Esto permite revertir el cambio si es necesario
            $table->dropColumn('comprobante_path');
        });
    }
};
