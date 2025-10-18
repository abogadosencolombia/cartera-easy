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
        Schema::table('pagos_caso', function (Blueprint $table) {
            // Añadimos la columna 'user_id'
            // ->nullable() es importante para que los registros antiguos no den error.
            // ->constrained('users') crea la relación con la tabla de usuarios.
            // ->onDelete('set null') si un usuario se elimina, el pago no se borra, solo se pone null este campo.
            $table->foreignId('user_id')->nullable()->after('caso_id')->constrained('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pagos_caso', function (Blueprint $table) {
            // Esto permite deshacer el cambio si es necesario
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};