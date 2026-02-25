<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persona_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained('personas')->onDelete('cascade');
            $table->string('nombre_original'); // Nombre real del archivo (ej: contrato.pdf)
            $table->string('ruta_archivo');    // Ruta en storage
            $table->string('mime_type')->nullable(); // pdf, jpg, etc
            $table->integer('size')->nullable(); // Peso en KB
            $table->foreignId('uploaded_by')->nullable()->constrained('users'); // Quién lo subió
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('persona_documentos');
    }
};