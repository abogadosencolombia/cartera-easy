<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->unique(['subscribable_type','subscribable_id','endpoint'], 'push_user_endpoint_unique');
        });
    }
    public function down(): void {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->dropUnique('push_user_endpoint_unique');
        });
    }
};