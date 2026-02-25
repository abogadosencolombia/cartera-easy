<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proceso_radicados', function (Blueprint $table) {
            // Relación con la etapa específica (El corazón del sistema)
            $table->foreignId('etapa_procesal_id')
                ->nullable() // Puede ser nulo al inicio
                ->after('estado') // Después de tu columna 'estado' actual
                ->constrained('etapas_procesales')
                ->nullOnDelete();
                
            // Fecha en que entró a esta etapa (Vital para calcular vencimientos vs SLA)
            $table->dateTime('fecha_cambio_etapa')->nullable()->after('etapa_procesal_id');
        });
    }

    public function down(): void
    {
        Schema::table('proceso_radicados', function (Blueprint $table) {
            $table->dropForeign(['etapa_procesal_id']);
            $table->dropColumn(['etapa_procesal_id', 'fecha_cambio_etapa']);
        });
    }
};