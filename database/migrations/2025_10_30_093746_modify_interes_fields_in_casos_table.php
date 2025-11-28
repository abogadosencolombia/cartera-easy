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
            // 1. Añadimos la nueva columna de fecha después de la tasa corriente
            $table->date('fecha_tasa_interes')->nullable()->after('tasa_interes_corriente');
            
            // 2. Eliminamos la columna de tasa moratoria
            $table->dropColumn('tasa_moratoria');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            // 1. Volvemos a añadir la columna de tasa moratoria
            $table->decimal('tasa_moratoria', 10, 2)->default(0)->after('tasa_interes_corriente');
            
            // 2. Eliminamos la nueva columna de fecha
            $table->dropColumn('fecha_tasa_interes');
        });
    }
};
