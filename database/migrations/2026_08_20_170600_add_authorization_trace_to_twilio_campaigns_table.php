<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('twilio_campaigns', function (Blueprint $table) {
            $table->char('raw_manifest_sha256', 64)->nullable()->index();
            $table->char('rne_proof_sha256', 64)->nullable()->index();
            $table->char('plan_sha256', 64)->nullable()->index();
            $table->string('rne_checked_at', 40)->nullable();
            $table->string('scheduled_at', 40)->nullable();
            $table->unsignedInteger('included_count')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('twilio_campaigns', function (Blueprint $table) {
            $table->dropColumn([
                'raw_manifest_sha256',
                'rne_proof_sha256',
                'plan_sha256',
                'rne_checked_at',
                'scheduled_at',
                'included_count',
            ]);
        });
    }
};
