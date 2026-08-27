<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('twilio_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('twilio_message_attempt_id')
                ->nullable()
                ->constrained('twilio_message_attempts')
                ->nullOnDelete();
            $table->string('message_sid', 34)->index();
            $table->char('event_hash', 64)->unique();
            $table->string('source', 24);
            $table->string('status', 32)->index();
            $table->string('previous_status', 32)->nullable();
            $table->boolean('applied')->default(false);
            $table->string('error_code', 32)->nullable();
            $table->text('error_message')->nullable();
            $table->longText('payload');
            $table->timestampTz('received_at');
            $table->timestamps();

            $table->index(
                ['twilio_message_attempt_id', 'received_at'],
                'twilio_status_events_attempt_received_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('twilio_status_events');
    }
};
