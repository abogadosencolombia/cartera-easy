<?php
namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class HonorariosController extends Controller
{
    public function index()
    {
        $stats = ['activeValue'=>0,'activeCount'=>0,'closedCount'=>0];
        $contratos = [];

        try {
            if (Schema::hasTable('contratos')) {
                $active = DB::table('contratos');
                if (Schema::hasColumn('contratos','estado')) {
                    $active->whereIn('estado',['ACTIVO','EN_PROCESO','VIGENTE']);
                }
                $stats['activeCount'] = (clone $active)->count();
                if (Schema::hasColumn('contratos','monto_total')) {
                    $stats['activeValue'] = (clone $active)->sum('monto_total');
                }
                if (Schema::hasColumn('contratos','estado')) {
                    $stats['closedCount'] = DB::table('contratos')->where('estado','CERRADO')->count();
                }

                $sel = ['id'];
                foreach (['cliente_id','cliente','cliente_nombre','monto_total','estado','created_at'] as $c) {
                    if (Schema::hasColumn('contratos',$c)) $sel[] = $c;
                }
                $contratos = DB::table('contratos')->select($sel)->orderByDesc('created_at')->limit(10)->get();
            }
        } catch (\Throwable $e) {}

        return Inertia::render('Gestion/Honorarios/Index', [
            'stats'=>$stats,
            'contratos'=>$contratos,
        ]);
    }
}
