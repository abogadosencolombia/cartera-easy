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
            // Creamos una nueva columna para guardar el ID del caso original.
            // Es 'nullable' porque los casos originales no son clones de nadie.
            // 'constrained' crea la relación. Si el caso original se borra, este campo se vuelve null.
            $table->foreignId('clonado_de_id')->nullable()->constrained('casos')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            // Esto permite deshacer la migración de forma segura.
            $table->dropForeign(['clonado_de_id']);
            $table->dropColumn('clonado_de_id');
        });
    }
};
