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
            $table->boolean('es_spoa_nunc')->default(false)->after('radicado');
        });

        Schema::table('proceso_radicados', function (Blueprint $table) {
            $table->boolean('es_spoa_nunc')->default(false)->after('radicado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            $table->dropColumn('es_spoa_nunc');
        });

        Schema::table('proceso_radicados', function (Blueprint $table) {
            $table->dropColumn('es_spoa_nunc');
        });
    }
};
