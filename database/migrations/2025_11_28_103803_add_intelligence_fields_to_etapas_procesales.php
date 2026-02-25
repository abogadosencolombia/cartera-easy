<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etapas_procesales', function (Blueprint $table) {
            // 1. Clasificación por Módulo (según documento maestro: 1 a 8)
            $table->unsignedTinyInteger('modulo_id')->default(2)->after('nombre')->comment('1:Pre-proc, 2:Inicio, 3:Defensa, 4:Audiencias, etc.');
            
            // 2. SLA (Tiempo límite en días hábiles)
            $table->unsignedInteger('sla_dias')->default(0)->after('descripcion')->comment('Días máximos para gestionar esta etapa');
            
            // 3. Riesgo Procesal (Para colorear el dashboard)
            $table->enum('riesgo', ['BAJO', 'MEDIO', 'ALTO', 'MUY_ALTO'])->default('BAJO')->after('sla_dias');
            
            // 4. Responsable (Quién tiene la pelota)
            $table->enum('responsable', ['ABOGADO', 'JUZGADO', 'CLIENTE', 'CONTRAPARTE', 'SISTEMA'])->default('ABOGADO')->after('riesgo');
            
            // 5. Trigger (Qué debe hacer el sistema automáticamente)
            $table->string('trigger_automatico')->nullable()->after('responsable')->comment('Acción automática: enviar_email, crear_tarea, bloquear_expediente');
        });
    }

    public function down(): void
    {
        Schema::table('etapas_procesales', function (Blueprint $table) {
            $table->dropColumn(['modulo_id', 'sla_dias', 'riesgo', 'responsable', 'trigger_automatico']);
        });
    }
};