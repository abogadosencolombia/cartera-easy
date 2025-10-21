<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // contrato_cargos
        Schema::table('contrato_cargos', function (Blueprint $table) {
            if (!Schema::hasColumn('contrato_cargos', 'fecha_inicio_intereses')) {
                // quita ->after('fecha_aplicado') si no existe en todos los entornos
                $table->date('fecha_inicio_intereses')->nullable()->after('fecha_aplicado');
            }
            if (!Schema::hasColumn('contrato_cargos', 'intereses_mora_acumulados')) {
                $table->decimal('intereses_mora_acumulados', 15, 2)->default(0)->after('monto');
            }
        });

        // contrato_cuotas
        Schema::table('contrato_cuotas', function (Blueprint $table) {
            if (!Schema::hasColumn('contrato_cuotas', 'intereses_mora_acumulados')) {
                $table->decimal('intereses_mora_acumulados', 15, 2)->default(0)->after('valor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contrato_cargos', function (Blueprint $table) {
            if (Schema::hasColumn('contrato_cargos', 'fecha_inicio_intereses')) {
                $table->dropColumn('fecha_inicio_intereses');
            }
            if (Schema::hasColumn('contrato_cargos', 'intereses_mora_acumulados')) {
                $table->dropColumn('intereses_mora_acumulados');
            }
        });

        Schema::table('contrato_cuotas', function (Blueprint $table) {
            if (Schema::hasColumn('contrato_cuotas', 'intereses_mora_acumulados')) {
                $table->dropColumn('intereses_mora_acumulados');
            }
        });
    }
};
