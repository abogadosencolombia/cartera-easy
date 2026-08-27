<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_suppressions', function (Blueprint $table) {
            $table->id();
            $table->char('phone_hash', 64)->unique();
            $table->text('phone_e164');
            $table->string('source', 64);
            $table->string('reason', 64);
            $table->timestampTz('first_suppressed_at');
            $table->timestampTz('last_suppressed_at');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_suppressions');
    }
};
