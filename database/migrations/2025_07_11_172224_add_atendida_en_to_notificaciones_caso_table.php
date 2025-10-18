<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notificaciones_caso', function (Blueprint $table) {
            $table->timestamp('atendida_en')->nullable()->after('leido');
        });
    }

    public function down(): void
    {
        Schema::table('notificaciones_caso', function (Blueprint $table) {
            $table->dropColumn('atendida_en');
        });
    }
};