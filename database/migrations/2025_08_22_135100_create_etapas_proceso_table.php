<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('etapas_proceso', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tipo_proceso_id')
                  ->constrained('tipos_proceso')
                  ->cascadeOnDelete();

            $table->string('nombre');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('final')->default(false);
            $table->timestamps();

            $table->unique(['tipo_proceso_id', 'nombre']); // no duplicar nombres dentro del mismo tipo
        });
    }

    public function down(): void {
        Schema::dropIfExists('etapas_proceso');
    }
};
