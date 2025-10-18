<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('process_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('process_stages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('order')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Una etapa puede aplicar a varios tipos y viceversa
        Schema::create('process_stage_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_stage_id')->constrained('process_stages')->cascadeOnDelete();
            $table->foreignId('process_type_id')->constrained('process_types')->cascadeOnDelete();
            $table->unique(['process_stage_id', 'process_type_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('process_stage_type');
        Schema::dropIfExists('process_stages');
        Schema::dropIfExists('process_types');
    }
};
