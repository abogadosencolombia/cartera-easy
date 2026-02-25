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
        Schema::table('casos', function (Blueprint $table) {
            // Agregamos la columna nota_cierre si no existe
            if (!Schema::hasColumn('casos', 'nota_cierre')) {
                $table->text('nota_cierre')->nullable()->after('notas_legales');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            $table->dropColumn('nota_cierre');
        });
    }
};