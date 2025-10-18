<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('integracion_externa_logs', function (Blueprint $table) {
            // ---- Columnas Principales ----

            $table->id(); // Un número único para identificar cada registro (como una cédula).

            // A qué servicio externo nos conectamos. Ej: 'CIFIN', 'Supersolidaria'.
            $table->string('servicio');

            // El punto específico al que llamamos. Ej: '/api/v1/validar_cooperativa'.
            $table->string('endpoint');

            // ---- Datos del Intercambio (guardados en formato JSON) ----

            // ¿Qué información le enviamos al servicio? (El cuerpo de nuestra carta).
            // Lo ponemos "nullable" por si alguna vez solo consultamos sin enviar datos.
            $table->json('datos_enviados')->nullable();

            // ¿Qué nos respondió el servicio? (La respuesta a nuestra carta).
            // También "nullable" por si la conexión falla y no hay respuesta.
            $table->json('respuesta')->nullable();

            // ---- Auditoría y Trazabilidad ----

            // Quién de nuestro sistema hizo la llamada (si fue un usuario).
            // Si el usuario se borra, este campo se pondrá en NULL para no causar errores.
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');

            // La fecha y hora exactas de cuándo se hizo la solicitud.
            // "useCurrent()" hace que la base de datos ponga la hora automáticamente.
            $table->timestamp('fecha_solicitud')->useCurrent();

            // Los campos "created_at" y "updated_at" que Laravel usa por defecto.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integracion_externa_logs');
    }
};
