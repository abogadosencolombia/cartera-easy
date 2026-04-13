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
            $table->json('checklist_seguimiento')->nullable();
        });

        Schema::table('proceso_radicados', function (Blueprint $table) {
            $table->json('checklist_seguimiento')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            $table->dropColumn('checklist_seguimiento');
        });

        Schema::table('proceso_radicados', function (Blueprint $table) {
            $table->dropColumn('checklist_seguimiento');
        });
    }
};
