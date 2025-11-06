<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // --- INICIO: CORRECCIÓN (SEPARAR EN MÚLTIPLES OPERACIONES) ---

        // --- OPERACIÓN 1: Añadir la nueva columna y eliminar la FK antigua ---
        Schema::table('documentos_caso', function (Blueprint $table) {
            
            // 1. Añadir la nueva columna 'persona_id' (nullable)
            $table->foreignId('persona_id')
                  ->nullable()
                  ->after('caso_id') // Opcional: define la posición de la columna
                  ->constrained('personas') // Asume que la tabla es 'personas'
                  ->nullOnDelete(); // Si la persona se elimina, este campo se vuelve null

            // 2. Manejar la columna 'codeudor_id' existente
            $foreignKeyName = 'documentos_caso_codeudor_id_foreign';

            $hasForeignKey = DB::select("
                SELECT 1
                FROM pg_constraint
                WHERE conname = ?
            ", [$foreignKeyName]);

            if (!empty($hasForeignKey)) {
                 // 2a. Eliminar la llave foránea incorrecta (la que apunta a 'personas')
                $table->dropForeign($foreignKeyName);
            }
        }); // <-- Aquí terminan y se ejecutan los cambios del esquema

        // --- OPERACIÓN 2: Mover los datos existentes ---
        // Ahora que la columna 'persona_id' existe, ejecutamos la limpieza.
        DB::table('documentos_caso as dc')
            ->join('personas as p', 'dc.codeudor_id', '=', 'p.id')
            ->update([
                'dc.persona_id' => DB::raw('dc.codeudor_id'), // Mueve el ID a la nueva columna
                'dc.codeudor_id' => null                 // Limpia la columna 'codeudor_id'
            ]);
       
        // --- OPERACIÓN 3: Añadir la nueva llave foránea correcta ---
        Schema::table('documentos_caso', function (Blueprint $table) {
            // 4. Opcional (Recomendado): Añadir la nueva llave foránea correcta
            // Ahora 'codeudor_id' (que debe estar vacía) apuntará a 'codeudores'.
             $table->foreign('codeudor_id')
                   ->references('id')
                   ->on('codeudores') // Asume que la tabla es 'codeudores'
                   ->nullOnDelete();
        });
        // --- FIN: CORRECCIÓN ---
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // El método 'down' también debe respetar la separación de operaciones
        
        // --- OPERACIÓN 1: Eliminar las nuevas FKs ---
        Schema::table('documentos_caso', function (Blueprint $table) {
            $table->dropForeign(['codeudor_id']); // Elimina la FK a 'codeudores'
            $table->dropForeign(['persona_id']);  // Elimina la FK a 'personas'
        });
        
        // --- OPERACIÓN 2: Mover los datos de vuelta ---
        DB::table('documentos_caso')
            ->whereNotNull('persona_id')
            ->update([
                'codeudor_id' => DB::raw('persona_id')
            ]);
        
        // --- OPERACIÓN 3: Eliminar columna y re-añadir FK antigua ---
        Schema::table('documentos_caso', function (Blueprint $table) {
            $table->dropColumn('persona_id');
            
             $table->foreign('codeudor_id', 'documentos_caso_codeudor_id_foreign')
                   ->references('id')
                   ->on('personas');
        });
    }
};

