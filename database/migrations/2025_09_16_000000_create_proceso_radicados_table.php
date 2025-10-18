<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('proceso_radicados', function (Blueprint $t) {
            $t->id();
            $t->string('abogado', 150)->nullable();
            $t->string('radicado', 80)->unique();
            $t->date('fecha_radicado')->nullable();
            $t->string('juzgado_entidad', 191)->nullable();
            $t->string('naturaleza', 120)->nullable();
            $t->string('tipo_proceso', 150)->nullable();
            $t->string('asunto', 500)->nullable();
            $t->string('demandante', 300)->nullable();
            $t->string('demandado', 300)->nullable();
            $t->string('correo_radicacion', 191)->nullable();
            $t->date('fecha_revision')->nullable();
            $t->string('responsable_revision', 120)->nullable();
            $t->date('fecha_proxima_revision')->nullable();
            $t->text('observaciones')->nullable();
            $t->text('ultima_actuacion')->nullable();
            $t->string('link_expediente', 1000)->nullable();
            $t->string('correos_juzgado', 500)->nullable();
            $t->string('ubicacion_drive', 500)->nullable();

            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proceso_radicados');
    }
};
