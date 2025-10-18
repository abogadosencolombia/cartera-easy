<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Evita el error 1060 si ya existe
        if (!Schema::hasColumn('contrato_cargos', 'fecha_inicio_intereses')) {
            Schema::table('contrato_cargos', function (Blueprint $table) {
                // quita ->after('fecha_aplicado') si esa columna no existe en todos los entornos
                $table->date('fecha_inicio_intereses')->nullable()->after('fecha_aplicado');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contrato_cargos', 'fecha_inicio_intereses')) {
            Schema::table('contrato_cargos', function (Blueprint $table) {
                $table->dropColumn('fecha_inicio_intereses');
            });
        }
    }
};
