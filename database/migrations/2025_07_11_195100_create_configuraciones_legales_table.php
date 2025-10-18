<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuraciones_legales', function (Blueprint $table) {
            $table->id();
            // Relación única con cada cooperativa
            $table->foreignId('cooperativa_id')->unique()->constrained()->onDelete('cascade');
            $table->decimal('tasa_maxima_usura', 5, 2)->comment('Tasa de usura configurada para la cooperativa.');
            $table->integer('dias_maximo_para_demandar')->default(120)->comment('Plazo máximo en días para radicar demanda desde la asignación.');
            $table->boolean('exige_pagare')->default(true);
            $table->boolean('exige_carta_instrucciones')->default(true);
            $table->boolean('exige_certificacion_saldo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones_legales');
    }
};