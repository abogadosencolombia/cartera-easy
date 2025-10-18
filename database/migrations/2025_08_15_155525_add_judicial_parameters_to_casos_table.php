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
        // 1. Creamos una nueva tabla para los Juzgados
        Schema::create('juzgados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('ciudad')->nullable();
            $table->string('tipo')->nullable();
            $table->timestamps();
        });

        // 2. Añadimos las nuevas columnas opcionales a la tabla 'casos'
        Schema::table('casos', function (Blueprint $table) {
            $table->string('subtipo_proceso')->nullable()->after('tipo_proceso');
            $table->string('etapa_procesal')->nullable()->after('subtipo_proceso');
            
            // Creamos la relación con la nueva tabla de juzgados
            // onDelete('set null') significa que si un juzgado se elimina, el campo en el caso se volverá null.
            $table->foreignId('juzgado_id')->nullable()->after('estado_proceso')->constrained('juzgados')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('casos', function (Blueprint $table) {
            $table->dropForeign(['juzgado_id']);
            $table->dropColumn(['subtipo_proceso', 'etapa_procesal', 'juzgado_id']);
        });

        Schema::dropIfExists('juzgados');
    }
};
