<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            if (!Schema::hasColumn('casos', 'integridad_score')) {
                $table->unsignedTinyInteger('integridad_score')->default(0)->index();
            }

            if (!Schema::hasColumn('casos', 'integridad_resumen')) {
                $table->jsonb('integridad_resumen')->nullable();
            }
        });

        Schema::table('proceso_radicados', function (Blueprint $table) {
            if (!Schema::hasColumn('proceso_radicados', 'integridad_score')) {
                $table->unsignedTinyInteger('integridad_score')->default(0)->index();
            }

            if (!Schema::hasColumn('proceso_radicados', 'integridad_resumen')) {
                $table->jsonb('integridad_resumen')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            $drop = array_filter(['integridad_score', 'integridad_resumen'], fn ($column) => Schema::hasColumn('casos', $column));
            if (!empty($drop)) {
                $table->dropColumn($drop);
            }
        });

        Schema::table('proceso_radicados', function (Blueprint $table) {
            $drop = array_filter(['integridad_score', 'integridad_resumen'], fn ($column) => Schema::hasColumn('proceso_radicados', $column));
            if (!empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};
