<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('documentos_legales', function (Blueprint $table) {
            $table->id(); // ID único para cada documento.

            // --- La Conexión Clave ---
            // Esta línea crea una columna 'cooperativa_id'.
            // 'constrained()' le dice a Laravel que esta columna se refiere al 'id' de la tabla 'cooperativas'.
            // 'onDelete('cascade')' es una regla de seguridad: si se borra una cooperativa, todos sus documentos asociados se borrarán automáticamente.
            // Esto mantiene nuestra base de datos limpia y consistente.
            $table->foreignId('cooperativa_id')->constrained()->onDelete('cascade');

            // --- Información del Documento ---
            $table->enum('tipo_documento', ['Poder', 'Certificado Existencia', 'Carta Autorización', 'Protocolo Interno']);
            $table->string('archivo'); // Aquí guardaremos la ruta o el nombre del archivo físico.
            $table->date('fecha_expedicion'); // Cuándo se emitió el documento.
            $table->date('fecha_vencimiento')->nullable(); // Cuándo vence, si aplica. 'nullable()' porque no todos los documentos vencen.

            $table->timestamps(); // 'created_at' y 'updated_at' para saber cuándo se subió o modificó el registro.
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('documentos_legales');
    }
};
