<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('casos', 'especialidad_id')) {
            Schema::table('casos', function (Blueprint $table) {
                $table->unsignedBigInteger('especialidad_id')->nullable(); // sin ->after()
            });

            // Agrega FK solo si existe la tabla referenciada
            if (Schema::hasTable('especialidades_juridicas')) {
                Schema::table('casos', function (Blueprint $table) {
                    $table->foreign('especialidad_id')
                          ->references('id')->on('especialidades_juridicas')
                          ->nullOnDelete();
                });
            }

            Schema::table('casos', function (Blueprint $table) {
                $table->index('especialidad_id', 'casos_especialidad_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('casos', 'especialidad_id')) {
            Schema::table('casos', function (Blueprint $table) {
                // si existe, Laravel resuelve el nombre por defecto
                $table->dropForeign(['especialidad_id']);
                $table->dropIndex('casos_especialidad_idx');
                $table->dropColumn('especialidad_id');
            });
        }
    }
};