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
            // Añadimos la deuda actual (lo que deben HOY)
            $table->decimal('monto_deuda_actual', 15, 2)->nullable()->after('monto_total');
            // Añadimos lo que ya han pagado
            $table->decimal('monto_total_pagado', 15, 2)->nullable()->after('monto_deuda_actual');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            //
        });
    }
};
