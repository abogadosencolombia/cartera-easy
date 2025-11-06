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
            // 1. Añade la columna para el ID del caso
            $table->foreignId('caso_id')
                ->nullable() // Permite que un contrato no esté ligado a un caso (opcional, pero seguro)
                ->unique()   // Asegura que un caso solo tenga UN contrato (relación 1 a 1)
                ->constrained('casos') // Define la llave foránea a la tabla 'casos'
                ->nullOnDelete(); // Si se borra el caso, este campo (caso_id) se vuelve NULL
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            // 1. Elimina la llave foránea (el nombre debe coincidir)
            $table->dropForeign(['caso_id']);
            
            // 2. Elimina la columna
            $table->dropColumn('caso_id');
        });
    }
};