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
        // 1. Agregar papelera a Usuarios
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes(); // Crea la columna 'deleted_at'
        });

        // 2. Agregar papelera a Casos
        Schema::table('casos', function (Blueprint $table) {
            $table->softDeletes();
        });

        // 3. Agregar papelera a Personas (opcional pero recomendado si usas Cascade)
        if (Schema::hasTable('personas')) {
            Schema::table('personas', function (Blueprint $table) {
                 if (!Schema::hasColumn('personas', 'deleted_at')) {
                     $table->softDeletes();
                 }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('casos', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        
        if (Schema::hasTable('personas')) {
             Schema::table('personas', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};