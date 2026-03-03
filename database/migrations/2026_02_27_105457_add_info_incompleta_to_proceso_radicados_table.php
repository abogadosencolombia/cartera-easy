<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proceso_radicados', function (Blueprint $blueprint) {
            $blueprint->boolean('info_incompleta')->default(false)->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('proceso_radicados', function (Blueprint $blueprint) {
            $blueprint->dropColumn('info_incompleta');
        });
    }
};
