<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('especialidad_user', function (Blueprint $table) {
            $table->primary(['user_id', 'especialidad_id']);
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // ===== ¡LA CORRECCIÓN ESTÁ AQUÍ! =====
            // Le decimos explícitamente que la tabla se llama 'especialidades' (plural).
            $table->foreignId('especialidad_id')->constrained('especialidades')->onDelete('cascade');
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('especialidad_user');
    }
};

