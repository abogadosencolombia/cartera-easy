<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('nota_gestions')) {
            Schema::create('nota_gestions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->text('descripcion');
                $table->string('despacho');
                $table->string('termino');
                $table->nullableMorphs('relacionable'); 
                $table->boolean('is_completed')->default(false);
                $table->timestamp('expires_at');
                $table->boolean('notified_before')->default(false);
                $table->boolean('notified_after')->default(false);
                $table->timestamp('last_periodic_notification_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No borramos para no perder datos si ya existiera
    }
};
