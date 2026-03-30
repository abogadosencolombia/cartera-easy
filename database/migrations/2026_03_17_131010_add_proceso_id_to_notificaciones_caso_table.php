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
        Schema::table('notificaciones_caso', function (Blueprint $table) {
            $table->foreignId('caso_id')->nullable()->change();
            $table->foreignId('proceso_id')->nullable()->after('caso_id')->constrained('proceso_radicados')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notificaciones_caso', function (Blueprint $table) {
            $table->foreignId('caso_id')->nullable(false)->change();
            $table->dropForeign(['proceso_id']);
            $table->dropColumn('proceso_id');
        });
    }
};
