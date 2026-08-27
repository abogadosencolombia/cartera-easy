<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('twilio_message_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('message_sid')->unique();
            $table->string('account_sid')->nullable();
            $table->string('from')->nullable();
            $table->string('to')->nullable();
            $table->string('status')->index();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->json('payload');
            $table->timestampTz('last_callback_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('twilio_message_statuses');
    }
};
