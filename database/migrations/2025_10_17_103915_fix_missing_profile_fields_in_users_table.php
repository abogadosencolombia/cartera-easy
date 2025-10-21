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
        // Verifica si la tabla ya tiene las columnas antes de intentar añadirlas
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'direccion')) {
                $table->string('direccion')->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'ciudad')) {
                $table->string('ciudad')->nullable()->after('direccion');
            }
            if (!Schema::hasColumn('users', 'linkedin_url')) {
                $table->string('linkedin_url')->nullable()->after('ciudad');
            }
            if (!Schema::hasColumn('users', 'tarjeta_profesional_url')) {
                $table->string('tarjeta_profesional_url')->nullable()->after('linkedin_url');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Elimina solo las columnas que existen
            $table->dropColumn(['direccion', 'ciudad', 'linkedin_url', 'tarjeta_profesional_url']);
        });
    }
};