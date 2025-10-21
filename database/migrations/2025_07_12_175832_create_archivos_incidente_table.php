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
        Schema::create('archivos_incidente', function (Blueprint $table) {
            $table->id();
            // A qué incidente pertenece este archivo.
            $table->foreignId('incidente_id')->constrained('incidentes_juridicos')->onDelete('cascade');
            // Quién subió el archivo.
            $table->foreignId('subido_por_id')->constrained('users');
            // Nombre original del archivo para referencia del usuario.
            $table->string('nombre_original');
            // Ruta donde guardamos el archivo en nuestro servidor.
            $table->string('ruta_archivo');
            // Tipo de archivo (ej: 'pdf', 'png', 'docx').
            $table->string('tipo_archivo', 50);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('archivos_incidente');
    }
};