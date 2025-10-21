<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contrato_cuotas', function (Blueprint $table) {
            $table->decimal('intereses_mora_acumulados', 14, 2)
                  ->default(0)
                  ->after('valor');
        });

        Schema::table('contrato_cargos', function (Blueprint $table) {
            $table->decimal('intereses_mora_acumulados', 14, 2)
                  ->default(0)
                  ->after('monto');
        });
    }

    public function down(): void
    {
        Schema::table('contrato_cuotas', function (Blueprint $table) {
            $table->dropColumn('intereses_mora_acumulados');
        });

        Schema::table('contrato_cargos', function (Blueprint $table) {
            $table->dropColumn('intereses_mora_acumulados');
        });
    }
};
