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
        Schema::create('documento_procesos', function (Blueprint $table) {
            $table->id();
            
            // Llave foránea que conecta con la tabla 'proceso_radicados'.
            // onDelete('cascade') asegura que si se elimina un radicado, todos sus documentos se borren también.
            $table->foreignId('proceso_radicado_id')->constrained('proceso_radicados')->onDelete('cascade');

            // Llave foránea opcional que conecta con el usuario que subió el archivo.
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');

            $table->string('descripcion');
            $table->string('file_path'); // Ruta del archivo en el storage
            $table->string('file_name'); // Nombre original del archivo

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documento_procesos');
    }
};
