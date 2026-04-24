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
        Schema::table('proceso_radicados', function (Blueprint $table) {
            $table->string('a_favor_de')->nullable()->comment('Indica si estamos a favor del DEMANDANTE o DEMANDADO');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proceso_radicados', function (Blueprint $table) {
            $table->dropColumn('a_favor_de');
        });
    }
};
