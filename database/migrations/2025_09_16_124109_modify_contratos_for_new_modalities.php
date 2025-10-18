<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            // 1. Permitir que el monto total sea nulo
            $table->decimal('monto_total', 14, 2)->default(null)->nullable()->change();

            // 2. Cambiar modalidad a string primero (sin el CHECK constraint aquí)
            $table->string('modalidad')->default('CUOTAS')->change();

            // 3. Añadir nuevas columnas para Litis y Cuota Mixta
            $table->decimal('porcentaje_litis', 5, 2)->nullable()->after('anticipo');
            $table->decimal('monto_base_litis', 14, 2)->nullable()->after('porcentaje_litis');
        });

        // 4. Agregar CHECK constraint por separado
        // Primero eliminar cualquier constraint existente
        DB::statement('ALTER TABLE contratos DROP CONSTRAINT IF EXISTS contratos_modalidad_check');
        
        // Luego agregar el nuevo constraint
        DB::statement("ALTER TABLE contratos ADD CONSTRAINT contratos_modalidad_check CHECK (modalidad IN ('CUOTAS', 'PAGO_UNICO', 'LITIS', 'CUOTA_MIXTA'))");
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            // Revertir los cambios en el orden inverso
            $table->dropColumn(['porcentaje_litis', 'monto_base_litis']);
            
            $table->decimal('monto_total', 14, 2)->default(0)->nullable(false)->change();
        });

        // Eliminar el constraint personalizado
        DB::statement('ALTER TABLE contratos DROP CONSTRAINT IF EXISTS contratos_modalidad_check');
        
        // Si tenías un constraint anterior, puedes recrearlo aquí si es necesario
        Schema::table('contratos', function (Blueprint $table) {
            $table->string('modalidad')->default('CUOTAS')->change();
        });
    }
};