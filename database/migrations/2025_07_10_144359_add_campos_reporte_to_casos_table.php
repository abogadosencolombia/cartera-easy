<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            // MEJORA IMPLEMENTADA: Campo para optimizar la búsqueda del último pago.
            $table->date('fecha_ultimo_pago')->nullable()->after('monto_total');
        });
    }

    public function down(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            $table->dropColumn('fecha_ultimo_pago');
        });
    }
};
