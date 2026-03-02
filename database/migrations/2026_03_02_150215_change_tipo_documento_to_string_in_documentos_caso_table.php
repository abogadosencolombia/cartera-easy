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
        Schema::table('documentos_caso', function (Blueprint $table) {
            // Cambiamos el enum a string para permitir cualquier tipo de documento validado por el controlador
            $table->string('tipo_documento', 255)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documentos_caso', function (Blueprint $table) {
            // Revertimos a enum (aunque esto puede fallar si hay datos que no coinciden)
            $table->enum('tipo_documento', [
                'pagaré', 
                'carta instrucciones', 
                'certificación saldo', 
                'libranza', 
                'cédula deudor', 
                'cédula codeudor', 
                'otros'
            ])->change();
        });
    }
};
