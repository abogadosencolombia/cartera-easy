<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('documento_procesos', function (Blueprint $table) {
            $table->string('descripcion', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('documento_procesos', function (Blueprint $table) {
            $table->string('descripcion', 255)->nullable(false)->default('')->change();
        });
    }
};
