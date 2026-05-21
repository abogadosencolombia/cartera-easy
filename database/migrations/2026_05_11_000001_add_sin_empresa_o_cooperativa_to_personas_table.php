<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            if (!Schema::hasColumn('personas', 'sin_empresa_o_cooperativa')) {
                $table->boolean('sin_empresa_o_cooperativa')->default(false)->after('es_demandado');
            }
        });

        $specialCooperativaId = DB::table('cooperativas')
            ->where('id', 9)
            ->orWhereRaw('LOWER(nombre) LIKE ?', ['sin empresa%'])
            ->orWhereRaw('LOWER(nombre) LIKE ?', ['si empresa%'])
            ->orWhereRaw('LOWER(nombre) LIKE ?', ['%empresa o cooperativa%'])
            ->orderByRaw('CASE WHEN id = 9 THEN 0 ELSE 1 END')
            ->value('id');

        if ($specialCooperativaId) {
            DB::table('personas')
                ->whereIn('id', function ($query) use ($specialCooperativaId) {
                    $query->select('persona_id')
                        ->from('cooperativa_persona')
                        ->where('cooperativa_id', $specialCooperativaId);
                })
                ->update(['sin_empresa_o_cooperativa' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            if (Schema::hasColumn('personas', 'sin_empresa_o_cooperativa')) {
                $table->dropColumn('sin_empresa_o_cooperativa');
            }
        });
    }
};
