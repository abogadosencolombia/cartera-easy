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
            $table->jsonb('viabilidad_juridica')->nullable();
            $table->string('viabilidad_estado', 20)->default('pendiente')->index();
        });

        Schema::table('proceso_radicados', function (Blueprint $table) {
            $table->jsonb('viabilidad_juridica')->nullable();
            $table->string('viabilidad_estado', 20)->default('pendiente')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            $table->dropColumn(['viabilidad_juridica', 'viabilidad_estado']);
        });

        Schema::table('proceso_radicados', function (Blueprint $table) {
            $table->dropColumn(['viabilidad_juridica', 'viabilidad_estado']);
        });
    }
};
