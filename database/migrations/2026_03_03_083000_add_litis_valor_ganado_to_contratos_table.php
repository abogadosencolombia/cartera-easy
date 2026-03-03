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
        Schema::table('contratos', function (Blueprint $table) {
            if (!Schema::hasColumn('contratos', 'litis_valor_ganado')) {
                $table->decimal('litis_valor_ganado', 14, 2)->nullable()->after('monto_base_litis');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn('litis_valor_ganado');
        });
    }
};
