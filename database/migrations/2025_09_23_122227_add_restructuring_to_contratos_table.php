<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Añade una nueva columna para registrar el origen de la reestructuración.
        Schema::table('contratos', function (Blueprint $table) {
            $table->foreignId('contrato_origen_id')->nullable()->constrained('contratos')->nullOnDelete()->after('id');
        });

        // Modifica el CHECK constraint para incluir 'REESTRUCTURADO'
        // Primero eliminar el constraint existente
        DB::statement('ALTER TABLE contratos DROP CONSTRAINT IF EXISTS contratos_estado_check');
        
        // Luego agregar el nuevo constraint con el valor adicional
        DB::statement("ALTER TABLE contratos ADD CONSTRAINT contratos_estado_check CHECK (estado IN ('ACTIVO', 'PAGOS_PENDIENTES', 'EN_MORA', 'REESTRUCTURADO', 'CERRADO'))");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropForeign(['contrato_origen_id']);
            $table->dropColumn('contrato_origen_id');
        });

        // Revertir el constraint sin 'REESTRUCTURADO'
        DB::statement('ALTER TABLE contratos DROP CONSTRAINT IF EXISTS contratos_estado_check');
        DB::statement("ALTER TABLE contratos ADD CONSTRAINT contratos_estado_check CHECK (estado IN ('ACTIVO', 'PAGOS_PENDIENTES', 'EN_MORA', 'CERRADO'))");
    }
};