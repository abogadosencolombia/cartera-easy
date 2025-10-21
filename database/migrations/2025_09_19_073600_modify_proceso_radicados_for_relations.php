<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proceso_radicados', function (Blueprint $table) {
            // nuevas FK (solo si no existen)
            if (!Schema::hasColumn('proceso_radicados', 'abogado_id')) {
                $table->foreignId('abogado_id')->nullable()->after('radicado')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('proceso_radicados', 'responsable_revision_id')) {
                $table->foreignId('responsable_revision_id')->nullable()->after('abogado_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('proceso_radicados', 'juzgado_id')) {
                $table->foreignId('juzgado_id')->nullable()->after('responsable_revision_id')->constrained('juzgados')->nullOnDelete();
            }
            if (!Schema::hasColumn('proceso_radicados', 'tipo_proceso_id')) {
                $table->foreignId('tipo_proceso_id')->nullable()->after('juzgado_id')->constrained('tipos_proceso')->nullOnDelete();
            }
            if (!Schema::hasColumn('proceso_radicados', 'demandante_id')) {
                $table->foreignId('demandante_id')->nullable()->after('tipo_proceso_id')->constrained('personas')->nullOnDelete();
            }
            if (!Schema::hasColumn('proceso_radicados', 'demandado_id')) {
                $table->foreignId('demandado_id')->nullable()->after('demandante_id')->constrained('personas')->nullOnDelete();
            }

            // columnas antiguas (solo si existen)
            $old = [];
            foreach (['abogado','responsable_revision','juzgado_entidad','tipo_proceso','demandante','demandado'] as $col) {
                if (Schema::hasColumn('proceso_radicados', $col)) $old[] = $col;
            }
            if (!empty($old)) {
                $table->dropColumn($old);
            }
        });
    }

    public function down(): void
    {
        Schema::table('proceso_radicados', function (Blueprint $table) {
            // recrea columnas antiguas
            foreach (['abogado','responsable_revision','juzgado_entidad','tipo_proceso','demandante','demandado'] as $col) {
                if (!Schema::hasColumn('proceso_radicados', $col)) {
                    $table->string($col)->nullable();
                }
            }

            // elimina FKs/columnas si existen
            foreach ([
                'abogado_id','responsable_revision_id','juzgado_id',
                'tipo_proceso_id','demandante_id','demandado_id'
            ] as $fk) {
                if (Schema::hasColumn('proceso_radicados', $fk)) {
                    // nombre de índice puede variar; dropForeign acepta arreglo
                    $table->dropForeign([$fk]);
                    $table->dropColumn($fk);
                }
            }
        });
    }
};
