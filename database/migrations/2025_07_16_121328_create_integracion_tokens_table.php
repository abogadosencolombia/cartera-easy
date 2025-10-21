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
    Schema::create('integracion_tokens', function (Blueprint $table) {
        $table->id();
        // A qué proveedor pertenecen estas credenciales (Ej: 'CIFIN', 'Supersolidaria')
        $table->string('proveedor')->unique();
        // La API Key, si la usan
        $table->text('api_key')->nullable();
        // El Client ID para autenticación OAuth2
        $table->text('client_id')->nullable();
        // El Client Secret para autenticación OAuth2
        $table->text('client_secret')->nullable();
        // Si el token tiene fecha de caducidad, la guardamos aquí
        $table->timestamp('expira_en')->nullable();
        // Un interruptor para activar o desactivar una integración fácilmente
        $table->boolean('activo')->default(true);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integracion_tokens');
    }
};
