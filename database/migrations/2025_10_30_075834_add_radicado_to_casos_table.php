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
            // Añadimos el campo 'radicado' después de 'referencia_credito'.
            // Es string, nullable y puede tener un índice para búsquedas más rápidas.
            $table->string('radicado')->nullable()->after('referencia_credito')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            // Elimina el índice y la columna
            $table->dropIndex(['radicado']);
            $table->dropColumn('radicado');
        });
    }
};
