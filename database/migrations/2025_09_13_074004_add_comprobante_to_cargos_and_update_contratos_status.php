<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Añadimos la columna para el comprobante en la tabla de cargos
        Schema::table('contrato_cargos', function (Blueprint $table) {
            $table->string('comprobante')->nullable()->after('descripcion');
        });

        // 2. Modificamos la tabla de contratos para aceptar el nuevo estado.
        // Es importante listar todos los estados posibles.
        Schema::table('contratos', function (Blueprint $table) {
            $table->string('estado')->default('ACTIVO')->change();
        });
    }

    public function down(): void
    {
        // Esto permite revertir los cambios si algo sale mal
        Schema::table('contrato_cargos', function (Blueprint $table) {
            $table->dropColumn('comprobante');
        });

        Schema::table('contratos', function (Blueprint $table) {
            $table->string('estado')->default('ACTIVO')->change();
        });
    }
};