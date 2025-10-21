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
        Schema::create('contrato_cargos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('contratos')->onDelete('cascade');

            // El tipo de cargo que estamos registrando
            $table->string('tipo'); // Ej: GASTO_REEMBOLSABLE, CIERRE_ATIPICO, INTERES_MORA

            $table->decimal('monto', 15, 2);
            $table->text('descripcion')->nullable();
            $table->date('fecha_aplicado');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contrato_cargos');
    }
};