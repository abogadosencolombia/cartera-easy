<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('twilio_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_key', 128)->unique();
            $table->string('name');
            $table->string('source_reference', 512)->nullable();
            $table->longText('manifest');
            $table->char('manifest_sha256', 64)->unique();
            $table->unsignedInteger('recipient_count');
            $table->string('status', 32)->index();
            $table->timestampTz('sealed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('twilio_campaigns');
    }
};
