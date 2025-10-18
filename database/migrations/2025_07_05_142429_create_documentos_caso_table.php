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
        Schema::create('documentos_caso', function (Blueprint $table) {
            $table->id();
            // Relación con la tabla 'casos'. Si se borra un caso, se borran sus documentos.
            $table->foreignId('caso_id')->constrained()->onDelete('cascade');
            
            // Tipo de documento, con una lista predefinida para mantener la consistencia.
            $table->enum('tipo_documento', [
                'pagaré', 
                'carta instrucciones', 
                'certificación saldo', 
                'libranza', 
                'cédula deudor', 
                'cédula codeudor', 
                'otros'
            ]);

            // Aquí se guardará la ruta física del archivo en el servidor.
            $table->string('archivo');
            $table->date('fecha_carga');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documentos_caso');
    }
};
