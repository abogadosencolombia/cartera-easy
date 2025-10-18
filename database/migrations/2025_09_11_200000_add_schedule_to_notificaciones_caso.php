<?php

// database/migrations/2025_09_11_200000_add_schedule_to_notificaciones_caso.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('notificaciones_caso', function (Blueprint $table) {
            if (!Schema::hasColumn('notificaciones_caso','programado_en')) {
                $table->dateTime('programado_en')->nullable()->after('mensaje');
            }
            if (!Schema::hasColumn('notificaciones_caso','last_sent_at')) {
                $table->dateTime('last_sent_at')->nullable()->after('programado_en');
            }
            if (!Schema::hasColumn('notificaciones_caso','completed')) {
                $table->boolean('completed')->default(false)->after('last_sent_at');
            }
        });
    }
    public function down(): void {
        Schema::table('notificaciones_caso', function (Blueprint $table) {
            $table->dropColumn(['programado_en','last_sent_at','completed']);
        });
    }
};
