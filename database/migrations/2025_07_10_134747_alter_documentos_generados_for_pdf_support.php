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
        Schema::table('documentos_generados', function (Blueprint $table) {
            // Renombramos las columnas existentes para mayor claridad
            $table->renameColumn('nombre_archivo', 'nombre_base');
            $table->renameColumn('ruta_archivo', 'ruta_archivo_docx');
            
            // Añadimos la nueva columna para la ruta del PDF
            $table->string('ruta_archivo_pdf')->after('ruta_archivo_docx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documentos_generados', function (Blueprint $table) {
            // Esto permite revertir los cambios si fuera necesario
            $table->dropColumn('ruta_archivo_pdf');
            $table->renameColumn('ruta_archivo_docx', 'ruta_archivo');
            $table->renameColumn('nombre_base', 'nombre_archivo');
        });
    }
};
