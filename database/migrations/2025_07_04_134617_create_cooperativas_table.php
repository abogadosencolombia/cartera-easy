<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Las migraciones en Laravel son como un control de versiones para tu base de datos.
// Esta clase define la estructura de nuestra tabla 'cooperativas'.

return new class extends Migration
{
    /**
     * Run the migrations.
     * Este método se ejecuta cuando aplicamos la migración (hacia adelante).
     * Aquí es donde creamos la tabla y sus columnas.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('cooperativas', function (Blueprint $table) {
            // --- Identificación Principal de la Cooperativa ---
            $table->id(); // Crea una columna 'id' numérica, autoincremental y como llave primaria. Es el estándar.
            $table->string('nombre')->unique(); // Columna de texto para el nombre. 'unique()' asegura que no haya dos cooperativas con el mismo nombre.
            $table->string('NIT')->unique(); // Columna de texto para el NIT. También debe ser único.

            // --- Información Regulatoria y de Constitución ---
            $table->enum('tipo_vigilancia', ['Supersolidaria', 'SFC', 'Otro']); // 'enum' restringe los valores a una lista predefinida. Muy útil para datos controlados.
            $table->date('fecha_constitucion'); // Campo de tipo fecha.
            $table->string('numero_matricula_mercantil')->nullable(); // 'nullable()' significa que este campo puede dejarse vacío.
            $table->enum('tipo_persona', ['Natural', 'Jurídica']);
            $table->string('representante_legal_nombre');
            $table->string('representante_legal_cedula');

            // --- Información de Contacto ---
            $table->string('contacto_nombre');
            $table->string('contacto_telefono');
            $table->string('contacto_correo');
            $table->string('correo_notificaciones_judiciales'); // Correo específico para asuntos legales.

            // --- Políticas de Operación y Cobranza ---
            $table->boolean('usa_libranza')->default(false); // Campo booleano (verdadero/falso). 'default(false)' establece 'no' como valor por defecto.
            $table->boolean('requiere_carta_instrucciones')->default(true); // Por defecto, asumimos que sí la requiere.
            $table->enum('tipo_garantia_frecuente', ['codeudor', 'hipotecaria', 'prendaria', 'sin garantía']);
            $table->string('tasa_maxima_moratoria'); // Lo guardamos como string para tener flexibilidad en el formato (ej: "2.5%" o "Tasa Usura").

            // --- Información Adicional ---
            $table->string('ciudad_principal_operacion')->nullable();
            $table->boolean('estado_activo')->default(true); // Por defecto, una nueva cooperativa está activa.
            $table->json('configuraciones_adicionales')->nullable(); // 'json' es un campo poderoso para guardar datos flexibles y estructurados sin tener que añadir más columnas en el futuro.

            // --- Marcas de Tiempo Automáticas ---
            $table->timestamps(); // Crea automáticamente las columnas 'created_at' y 'updated_at'. Laravel las gestiona por nosotros.
        });
    }

    /**
     * Reverse the migrations.
     * Este método se ejecuta si necesitamos deshacer la migración (hacia atrás).
     * Aquí eliminamos la tabla que creamos en 'up()'.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('cooperativas');
    }
};
