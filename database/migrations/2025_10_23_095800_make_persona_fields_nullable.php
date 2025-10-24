<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// El nombre de este archivo debe tener la fecha y hora actual
// Ejemplo: 2025_10_23_095800_make_persona_fields_nullable.php

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Esta migración cambia las columnas existentes para que acepten valores NULL
        Schema::table('personas', function (Blueprint $table) {
            
            // Campos de la migración original que eran obligatorios
            if (Schema::hasColumn('personas', 'celular_1')) {
                $table->string('celular_1')->nullable()->change();
            }
            if (Schema::hasColumn('personas', 'correo_1')) {
                $table->string('correo_1')->nullable()->change();
            }
            if (Schema::hasColumn('personas', 'direccion')) {
                $table->string('direccion')->nullable()->change();
            }
            if (Schema::hasColumn('personas', 'ciudad')) {
                $table->string('ciudad')->nullable()->change();
            }

            // Aseguramos que los demás también sean opcionales
            if (Schema::hasColumn('personas', 'fecha_expedicion')) {
                 $table->date('fecha_expedicion')->nullable()->change();
            }
            if (Schema::hasColumn('personas', 'telefono_fijo')) {
                $table->string('telefono_fijo')->nullable()->change();
            }
            if (Schema::hasColumn('personas', 'celular_2')) {
                $table->string('celular_2')->nullable()->change();
            }
            if (Schema::hasColumn('personas', 'correo_2')) {
                $table->string('correo_2')->nullable()->change();
            }
            if (Schema::hasColumn('personas', 'empresa')) {
                $table->string('empresa')->nullable()->change();
            }
            if (Schema::hasColumn('personas', 'cargo')) {
                $table->string('cargo')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Opcional: Revertir los cambios (aunque podría fallar si ya hay datos nulos)
        Schema::table('personas', function (Blueprint $table) {
            if (Schema::hasColumn('personas', 'celular_1')) {
                $table->string('celular_1')->nullable(false)->change();
            }
            if (Schema::hasColumn('personas', 'correo_1')) {
                $table->string('correo_1')->nullable(false)->change();
            }
            if (Schema::hasColumn('personas', 'direccion')) {
                $table->string('direccion')->nullable(false)->change();
            }
            if (Schema::hasColumn('personas', 'ciudad')) {
                $table->string('ciudad')->nullable(false)->change();
            }
        });
    }
};
