<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('codeudores', 'dv')) {
            Schema::table('codeudores', function (Blueprint $table) {
                $table->string('dv', 1)->nullable()->after('numero_documento');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('codeudores', 'dv')) {
            Schema::table('codeudores', function (Blueprint $table) {
                $table->dropColumn('dv');
            });
        }
    }
};
