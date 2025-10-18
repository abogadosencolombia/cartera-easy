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
        Schema::create('documentos_generados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caso_id')->constrained()->onDelete('cascade');
            
            // --- LA COLUMNA CLAVE QUE FALTABA ---
            // La definimos como 'nullable' y 'constrained' para que, si se borra una plantilla,
            // el registro del documento generado no se pierda, solo pierda la referencia.
            $table->foreignId('plantilla_documento_id')->nullable()->constrained('plantillas_documento')->onDelete('set null');
            
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('nombre_archivo');
            $table->string('ruta_archivo');
            $table->string('version_plantilla');
            $table->text('observaciones')->nullable();
            $table->boolean('es_confidencial')->default(false);
            $table->json('metadatos')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documentos_generados');
    }
};
