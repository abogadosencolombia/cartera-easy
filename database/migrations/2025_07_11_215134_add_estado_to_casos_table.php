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
            // Añadimos la columna 'estado' después de la columna 'tipo_garantia_asociada'
            // Puedes cambiar 'tipo_garantia_asociada' por otra columna si prefieres.
            $table->string('estado')->default('activo')->after('tipo_garantia_asociada');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
};