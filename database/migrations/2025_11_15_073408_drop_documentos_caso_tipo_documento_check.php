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
        Schema::table('documentos_caso', function (Blueprint $table) {
            // Este comando es específico de PostgreSQL para eliminar la regla
            DB::statement('ALTER TABLE documentos_caso DROP CONSTRAINT IF EXISTS documentos_caso_tipo_documento_check');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documentos_caso', function (Blueprint $table) {
            // Esto vuelve a añadir la regla original (¡sin "demanda"!)
            DB::statement("ALTER TABLE documentos_caso ADD CONSTRAINT documentos_caso_tipo_documento_check CHECK (tipo_documento IN ('pagaré', 'carta instrucciones', 'certificación saldo', 'libranza', 'cédula deudor', 'cédula codeudor', 'otros'))");
        });
    }
};
