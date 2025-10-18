<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('subtipos_proceso')) {
            Schema::create('subtipos_proceso', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tipo_proceso_id')
                      ->constrained('tipos_proceso')
                      ->onDelete('cascade');
                $table->string('nombre');
                $table->string('descripcion')->nullable();
                $table->timestamps();
                $table->unique(['tipo_proceso_id','nombre'], 'subtipo_unique_por_tipo');
            });
        }
    }
    public function down(): void {
        Schema::dropIfExists('subtipos_proceso');
    }
};
