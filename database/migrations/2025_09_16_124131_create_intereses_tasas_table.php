<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intereses_tasas', function (Blueprint $table) {
            $table->id();
            $table->decimal('tasa_ea', 5, 2); // Para guardar la Tasa Efectiva Anual, ej: 35.50
            $table->date('fecha_vigencia'); // La fecha desde la cual esta tasa es válida
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intereses_tasas');
    }
};