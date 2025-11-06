<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Esta tabla conecta un Caso con múltiples Codeudores.
     */
    public function up(): void
    {
        Schema::create('caso_codeudor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caso_id')->constrained('casos')->onDelete('cascade');
            $table->foreignId('codeudor_id')->constrained('codeudores')->onDelete('cascade');
            $table->timestamps();

            // Evitar duplicados (mismo codeudor en el mismo caso)
            $table->unique(['caso_id', 'codeudor_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('caso_codeudor');
    }
};
