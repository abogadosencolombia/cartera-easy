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
        Schema::create('personas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_completo');
            $table->string('tipo_documento')->default('CC'); // Cédula de Ciudadanía por defecto
            $table->string('numero_documento')->unique(); // El documento debe ser único en todo el sistema
            $table->string('telefono_fijo')->nullable();
            $table->string('celular_1'); // Celular principal, obligatorio
            $table->string('celular_2')->nullable(); // Celular secundario, opcional
            $table->string('correo_1'); // Correo principal, obligatorio
            $table->string('correo_2')->nullable(); // Correo secundario, opcional
            $table->string('direccion');
            $table->string('ciudad');
            $table->string('empresa')->nullable(); // Empresa donde labora, opcional
            $table->string('cargo')->nullable();
            $table->text('observaciones')->nullable(); // Un campo de texto largo para notas generales
            $table->timestamps(); // Campos created_at y updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personas');
    }
};
