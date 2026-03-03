<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caso_user', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('caso_id')->constrained()->onDelete('cascade');
            $blueprint->foreignId('user_id')->constrained()->onDelete('cascade');
            $blueprint->timestamps();
        });

        // Migrar datos existentes de casos.user_id a la nueva tabla pivote
        $casos = \DB::table('casos')->whereNotNull('user_id')->get();
        foreach ($casos as $caso) {
            \DB::table('caso_user')->insert([
                'caso_id' => $caso->id,
                'user_id' => $caso->user_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('caso_user');
    }
};
