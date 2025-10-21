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
        Schema::table('contrato_cargos', function (Blueprint $table) {
            // Añadimos la columna para el ID del pago.
            $table->foreignId('pago_id')
                  ->nullable()
                  ->after('comprobante') // La ubicamos después de la columna 'comprobante'.
                  ->constrained('contrato_pagos') // Creamos la llave foránea a la tabla 'contrato_pagos'.
                  ->onDelete('set null'); // Si se borra el pago, el ID aquí se vuelve nulo.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contrato_cargos', function (Blueprint $table) {
            // Para revertir, primero eliminamos la llave foránea y luego la columna.
            $table->dropForeign(['pago_id']);
            $table->dropColumn('pago_id');
        });
    }
};