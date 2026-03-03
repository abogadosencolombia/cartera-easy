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
        Schema::table('documentos_legales', function (Blueprint $table) {
            $table->string('tipo_documento', 255)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documentos_legales', function (Blueprint $table) {
            $table->enum('tipo_documento', ['Poder', 'Certificado Existencia', 'Carta Autorización', 'Protocolo Interno'])->change();
        });
    }
};
