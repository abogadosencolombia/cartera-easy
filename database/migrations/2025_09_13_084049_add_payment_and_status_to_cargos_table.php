    <?php

    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration
    {
        public function up(): void
        {
            // 1. Añadimos un campo 'cargo_id' a la tabla de pagos.
            //    Esto nos permitirá saber si un pago corresponde a una cuota o a un cargo.
            Schema::table('contrato_pagos', function (Blueprint $table) {
                $table->foreignId('cargo_id')->nullable()->after('cuota_id')->constrained('contrato_cargos')->onDelete('set null');
            });

            // 2. Añadimos 'estado' y 'fecha_pago' a la tabla de cargos para un mejor control.
            Schema::table('contrato_cargos', function (Blueprint $table) {
                $table->string('estado')->default('PENDIENTE')->after('monto'); // PENDIENTE, PAGADO
                $table->date('fecha_pago')->nullable()->after('estado');
            });
        }

        public function down(): void
        {
            Schema::table('contrato_pagos', function (Blueprint $table) {
                // Se usa un array para asegurar que el drop funcione en diferentes sistemas de BD.
                $table->dropForeign(['cargo_id']);
                $table->dropColumn('cargo_id');
            });

            Schema::table('contrato_cargos', function (Blueprint $table) {
                $table->dropColumn(['estado', 'fecha_pago']);
            });
        }
    };