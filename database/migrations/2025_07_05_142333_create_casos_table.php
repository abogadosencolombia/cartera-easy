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
        Schema::create('casos', function (Blueprint $table) {
            $table->id();

            // --- Relaciones Clave (Foreign Keys) ---
            $table->foreignId('cooperativa_id')->constrained()->onDelete('cascade'); // A qué cooperativa pertenece el caso
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Qué usuario (abogado/gestor) está a cargo
            $table->foreignId('deudor_id')->constrained('personas')->onDelete('cascade'); // Quién es el deudor principal
            $table->foreignId('codeudor1_id')->nullable()->constrained('personas')->onDelete('set null'); // Codeudor 1 (opcional)
            $table->foreignId('codeudor2_id')->nullable()->constrained('personas')->onDelete('set null'); // Codeudor 2 (opcional)

            // --- Información del Crédito ---
            $table->string('referencia_credito')->nullable();
            $table->enum('tipo_proceso', ['CURADURIA','EJECUTIVO','RESTITUCION','LABORAL','PAGO DIRECTO','REGIMEN DE INSOLVENCIA','INSOLVENCIA ECONOMICA','PERSONAL','PROCESO VERBAL']);
            $table->enum('estado_proceso', ['prejurídico', 'demandado', 'en ejecución', 'sentencia', 'cerrado'])->default('prejurídico');
            $table->enum('tipo_garantia_asociada', ['codeudor', 'hipotecaria', 'prendaria', 'sin garantía']);
            $table->date('fecha_apertura');
            $table->date('fecha_vencimiento')->nullable();
            $table->decimal('monto_total', 15, 2); // 15 dígitos en total, 2 para decimales
            $table->decimal('tasa_interes_corriente', 5, 2);
            $table->decimal('tasa_moratoria', 5, 2);
            $table->enum('origen_documental', ['pagaré', 'libranza', 'contrato', 'otro']);

            // --- Gestión y Estado del Caso ---
            $table->boolean('bloqueado')->default(false);
            $table->string('motivo_bloqueo')->nullable();
            $table->string('etapa_actual')->nullable(); // Para describir la fase actual (ej: "Notificación enviada")
            $table->string('medio_contacto')->nullable(); // Último medio de contacto usado (ej: "Llamada", "Email")
            $table->timestamp('ultima_actividad')->nullable(); // Fecha y hora de la última acción
            $table->text('notas_legales')->nullable(); // Notas importantes del abogado/gestor

            $table->timestamps();

            // --- Índices para optimizar búsquedas ---
            $table->index(['cooperativa_id', 'estado_proceso', 'fecha_apertura']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('casos');
    }
};
