<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('twilio_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('twilio_campaign_id')
                ->constrained('twilio_campaigns')
                ->restrictOnDelete();
            $table->unsignedInteger('ordinal');
            $table->unsignedInteger('source_row')->nullable();
            $table->char('identity_hash', 64);
            $table->string('role', 32);
            $table->text('phone')->nullable();
            $table->char('phone_hash', 64)->nullable()->index();
            $table->text('body');
            $table->char('body_hash', 64);
            $table->unsignedSmallInteger('segments');
            $table->decimal('estimated_cost', 12, 6);
            $table->char('idempotency_key', 64)->unique();
            $table->text('exclusion_reason')->nullable();
            $table->string('status', 32)->index();
            $table->timestamps();

            $table->unique(
                ['twilio_campaign_id', 'ordinal'],
                'twilio_campaign_recipients_campaign_ordinal_unique'
            );
            $table->index(
                ['twilio_campaign_id', 'status'],
                'twilio_campaign_recipients_campaign_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('twilio_campaign_recipients');
    }
};
