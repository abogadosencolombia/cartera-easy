<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historial_mora', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caso_id')->constrained()->onDelete('cascade');
            $table->date('fecha_inicio_mora');
            $table->date('fecha_fin_mora')->nullable();
            $table->integer('dias_en_mora')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_mora');
    }
};
