<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('contrato_cuotas', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('contrato_id');
            $t->unsignedInteger('numero');
            $t->date('fecha_vencimiento')->nullable();
            $t->decimal('valor',14,2)->default(0);
            $t->string('estado')->default('PENDIENTE'); // PENDIENTE | PAGADA | EN_MORA
            $t->date('fecha_pago')->nullable();
            $t->timestamps();
            $t->index(['contrato_id','numero']);
        });
    }
    public function down(): void { Schema::dropIfExists('contrato_cuotas'); }
};
