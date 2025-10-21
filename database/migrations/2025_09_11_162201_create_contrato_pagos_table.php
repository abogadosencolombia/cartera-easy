<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('contrato_pagos', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('contrato_id');
            $t->unsignedBigInteger('cuota_id')->nullable();
            $t->decimal('valor',14,2)->default(0);
            $t->date('fecha')->nullable();
            $t->string('metodo')->nullable(); // EFECTIVO | TRANSFERENCIA | TARJETA | OTRO
            $t->text('nota')->nullable();
            $t->string('comprobante')->nullable();
            $t->timestamps();
            $t->index(['contrato_id','cuota_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('contrato_pagos'); }
};
