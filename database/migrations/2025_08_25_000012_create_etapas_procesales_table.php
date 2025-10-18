<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('etapas_procesales')) {
            Schema::create('etapas_procesales', function (Blueprint $table) {
                $table->id();
                $table->string('nombre')->unique();
                $table->unsignedTinyInteger('orden')->nullable(); // opcional para ordenar
                $table->string('descripcion')->nullable();
                $table->timestamps();
            });
        }
    }
    public function down(): void {
        Schema::dropIfExists('etapas_procesales');
    }
};
