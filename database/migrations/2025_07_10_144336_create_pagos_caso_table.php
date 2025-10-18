<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos_caso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caso_id')->constrained()->onDelete('cascade');
            $table->decimal('monto_pagado', 15, 2);
            $table->date('fecha_pago');
            // MEJORA IMPLEMENTADA: Clasificación del tipo de pago.
            $table->enum('motivo_pago', ['total', 'parcial', 'acuerdo', 'sentencia'])->default('parcial');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_caso');
    }
};
