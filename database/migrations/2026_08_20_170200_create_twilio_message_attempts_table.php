<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('twilio_message_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('twilio_campaign_recipient_id')
                ->constrained('twilio_campaign_recipients')
                ->restrictOnDelete();
            $table->unsignedSmallInteger('attempt_number')->default(1);
            $table->char('idempotency_key', 64)->unique();
            $table->string('message_sid', 34)->nullable()->unique();
            $table->string('status', 32)->index();
            $table->unsignedSmallInteger('segments')->nullable();
            $table->decimal('price', 12, 6)->nullable();
            $table->char('price_unit', 3)->nullable();
            $table->string('provider_error_code', 32)->nullable();
            $table->text('provider_error_message')->nullable();
            $table->longText('provider_response')->nullable();
            $table->timestampTz('submitted_at');
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('last_callback_at')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['twilio_campaign_recipient_id', 'attempt_number'],
                'twilio_message_attempts_recipient_number_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('twilio_message_attempts');
    }
};
