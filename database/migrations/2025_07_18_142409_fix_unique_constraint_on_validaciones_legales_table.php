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
        Schema::table('validaciones_legales', function (Blueprint $table) {
            // PASO 1: Eliminar la llave foránea que depende del índice.
            // Laravel nombra esta restricción automáticamente, así que le decimos que la busque por la columna.
            $table->dropForeign(['caso_id']);

            // PASO 2: Ahora sí, eliminamos la regla única incorrecta.
            $table->dropUnique('validaciones_legales_caso_id_tipo_unique');

            // PASO 3: Creamos la nueva regla correcta.
            $table->unique(['caso_id', 'requisito_id']);

            // PASO 4: Volvemos a crear la llave foránea para mantener la integridad de los datos.
            $table->foreign('caso_id')
                  ->references('id')
                  ->on('casos')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('validaciones_legales', function (Blueprint $table) {
            // Para revertir, hacemos los pasos contrarios en orden inverso.
            $table->dropForeign(['caso_id']);
            $table->dropUnique(['caso_id', 'requisito_id']);
            
            $table->unique(['caso_id', 'tipo'], 'validaciones_legales_caso_id_tipo_unique');
            
            $table->foreign('caso_id')
                  ->references('id')
                  ->on('casos')
                  ->onDelete('cascade');
        });
    }
};
