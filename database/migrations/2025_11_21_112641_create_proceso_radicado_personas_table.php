<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proceso_radicado_personas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proceso_radicado_id')->constrained()->onDelete('cascade');
            $table->foreignId('persona_id')->constrained()->onDelete('cascade');
            // 'tipo' definirá si es Demandante o Demandado
            $table->enum('tipo', ['DEMANDANTE', 'DEMANDADO']); 
            $table->timestamps();
        });

        // --- MIGRACIÓN DE DATOS EXISTENTES ---
        // Esto mueve los datos de las columnas antiguas a la nueva tabla pivot
        // para no perder información.
        $procesos = DB::table('proceso_radicados')->get();
        
        foreach ($procesos as $p) {
            if ($p->demandante_id) {
                DB::table('proceso_radicado_personas')->insert([
                    'proceso_radicado_id' => $p->id,
                    'persona_id' => $p->demandante_id,
                    'tipo' => 'DEMANDANTE',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            if ($p->demandado_id) {
                DB::table('proceso_radicado_personas')->insert([
                    'proceso_radicado_id' => $p->id,
                    'persona_id' => $p->demandado_id,
                    'tipo' => 'DEMANDADO',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Opcional: Eliminar las columnas viejas (Descomentar si estás seguro)
        // Schema::table('proceso_radicados', function (Blueprint $table) {
        //     $table->dropForeign(['demandante_id']);
        //     $table->dropForeign(['demandado_id']);
        //     $table->dropColumn(['demandante_id', 'demandado_id']);
        // });
    }

    public function down(): void
    {
        Schema::dropIfExists('proceso_radicado_personas');
    }
};