<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_proceso', function (Blueprint $table) {
            $table->foreignId('especialidad_juridica_id')
                  ->nullable()
                  ->after('id') // Opcional, por orden
                  ->constrained('especialidades_juridicas') // Tu tabla se llama así
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('tipos_proceso', function (Blueprint $table) {
            $table->dropForeign(['especialidad_juridica_id']);
            $table->dropColumn('especialidad_juridica_id');
        });
    }
};