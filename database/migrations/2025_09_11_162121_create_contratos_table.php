<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('contratos', function (Blueprint $t) {
            $t->id();
            $t->string('referencia')->nullable();
            $t->unsignedBigInteger('cliente_id');
            $t->decimal('monto_total',14,2)->default(0);
            $t->decimal('anticipo',14,2)->default(0);
            $t->string('modalidad')->default('CUOTAS'); // CUOTAS | PAGO_UNICO
            $t->string('estado')->default('ACTIVO');    // ACTIVO | CERRADO | BORRADOR
            $t->date('inicio')->nullable();
            $t->text('nota')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('contratos'); }
};
