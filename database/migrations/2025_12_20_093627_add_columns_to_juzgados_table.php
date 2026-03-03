<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('juzgados', function (Blueprint $table) {
            // Solo agregamos la columna si NO existe previamente
            if (!Schema::hasColumn('juzgados', 'distrito')) {
                $table->string('distrito')->nullable();
            }
            if (!Schema::hasColumn('juzgados', 'municipio')) {
                $table->string('municipio')->nullable();
            }
            if (!Schema::hasColumn('juzgados', 'departamento')) {
                $table->string('departamento')->nullable();
            }
            if (!Schema::hasColumn('juzgados', 'email')) {
                $table->string('email')->nullable(); // Para el correo del despacho
            }
            if (!Schema::hasColumn('juzgados', 'telefono')) {
                $table->string('telefono')->nullable();
            }
            // Aseguramos que tenga softDeletes para no perder datos por error
            if (!Schema::hasColumn('juzgados', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down()
    {
        // En este caso no borramos columnas al revertir para proteger tus datos
    }
};