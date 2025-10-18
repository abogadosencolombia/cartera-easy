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
        Schema::table('users', function (Blueprint $table) {
            $table->string('direccion')->nullable()->after('email');
            $table->string('ciudad')->nullable()->after('direccion');
            $table->string('linkedin_url')->nullable()->after('ciudad');
            $table->string('tarjeta_profesional_url')->nullable()->after('linkedin_url'); // Campo para el documento
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['direccion', 'ciudad', 'linkedin_url', 'tarjeta_profesional_url']);
        });
    }
};