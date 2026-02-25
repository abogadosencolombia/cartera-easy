<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Caso;
use App\Models\ProcesoRadicado;
use App\Models\Contrato;
use App\Models\RevisionDiaria;
use App\Models\User; 
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PendientesRevisionExport;

class RevisionDiariaController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $hoy = Carbon::today();

        $abogados = User::orderBy('name')->get(['id', 'name']);

        $filters = $request->only(
            'search_casos', 'search_radicados', 'search_contratos',
            'start_date', 'end_date',
            'abogado_id'
        );

        Log::debug('RevisionDiariaController: Filtros recibidos', $filters);

        $applyFilters = $this->getApplyFiltersClosure();

        // --- INICIO: Construir Consultas Base ---
        $casosQuery = Caso::query()->with(['deudor', 'cooperativa']);
        
        // CORRECCIÓN CRÍTICA AQUÍ: Cambiado 'demandante', 'demandado' por sus plurales
        // Esto carga la relación real definida en el modelo.
        $radicadosQuery = ProcesoRadicado::query()->with(['demandantes', 'demandados']);
        
        $contratosQuery = Contrato::query()->with(['cliente'])->where('estado', 'ACTIVO');
        // --- FIN: Construir Consultas Base ---

        $applyFilters($casosQuery, 'casos', $filters);
        $applyFilters($radicadosQuery, 'radicados', $filters);
        $applyFilters($contratosQuery, 'contratos', $filters);

        // ... resto del código (mantener igual hasta el final) ...
        
        // --- CALCULO DE PENDIENTES ---
        $baseFilteredCasosQuery = (clone $casosQuery);
        $baseFilteredRadicadosQuery = (clone $radicadosQuery);
        $baseFilteredContratosQuery = (clone $contratosQuery);

        $revisadosHoyCasoIds = RevisionDiaria::where('user_id', $user->id) ->where('fecha_revision', $hoy)->where('revisable_type', Caso::class)->pluck('revisable_id');
        $revisadosHoyRadicadoIds = RevisionDiaria::where('user_id', $user->id)->where('fecha_revision', $hoy)->where('revisable_type', ProcesoRadicado::class)->pluck('revisable_id');
        $revisadosHoyContratoIds = RevisionDiaria::where('user_id', $user->id)->where('fecha_revision', $hoy)->where('revisable_type', Contrato::class)->pluck('revisable_id');

        $pendientesCounts = [
            'casos' => (clone $baseFilteredCasosQuery)->whereNotIn('casos.id', $revisadosHoyCasoIds)->count(),
            'radicados' => (clone $baseFilteredRadicadosQuery)->whereNotIn('proceso_radicados.id', $revisadosHoyRadicadoIds)->count(),
            'contratos' => (clone $baseFilteredContratosQuery)->whereNotIn('contratos.id', $revisadosHoyContratoIds)->count(),
        ];

        // --- PAGINACIÓN ---
        $casos = (clone $baseFilteredCasosQuery)->orderBy('fecha_vencimiento', 'desc')->paginate(25, ['*'], 'casos_page')->appends($request->all());
        $radicados = (clone $baseFilteredRadicadosQuery)->orderBy('fecha_proxima_revision', 'desc')->paginate(25, ['*'], 'radicados_page')->appends($request->all());
        $contratos = (clone $baseFilteredContratosQuery)->orderBy('created_at', 'desc')->paginate(25, ['*'], 'contratos_page')->appends($request->all());

        // --- TRANSFORMACIÓN ---
        $addRevisadoStatus = function ($item) use ($user, $hoy) {
            $latestRevision = RevisionDiaria::where('user_id', $user->id)->where('revisable_id', $item->id)->where('revisable_type', get_class($item))->latest('fecha_revision')->first();
            if ($latestRevision) {
                    $item->revisadoHoy = Carbon::parse($latestRevision->fecha_revision)->isToday();
                    $item->ultimaRevisionFecha = Carbon::parse($latestRevision->fecha_revision)->toDateString();
            } else {
                    $item->revisadoHoy = false;
                    $item->ultimaRevisionFecha = null;
            }
            return $item;
        };
        $casos->getCollection()->transform($addRevisadoStatus);
        $radicados->getCollection()->transform($addRevisadoStatus);
        $contratos->getCollection()->transform($addRevisadoStatus);

        return Inertia::render('Revisiones/Index', [
            'casos' => $casos,
            'radicados' => $radicados,
            'contratos' => $contratos,
            'pendientesCounts' => $pendientesCounts,
            'filters' => $filters,
            'abogados' => $abogados,
        ]);
    }
    
    // ... Mantén el resto de métodos (toggle, export, getApplyFiltersClosure) exactamente igual ...
    // Solo asegúrate de que en exportPendientesExcel también cambies 'demandante' por 'demandantes'
    
    public function toggle(Request $request)
    {
         // ... (código original de toggle) ...
         $validated = $request->validate([ 'id' => 'required|integer', 'tipo' => 'required|string|in:Caso,ProcesoRadicado,Contrato']);
        $user = Auth::user();
        $hoy = Carbon::today();
        $modelMap = [ 'Caso' => Caso::class, 'ProcesoRadicado' => ProcesoRadicado::class, 'Contrato' => Contrato::class];
        $modelClass = $modelMap[$validated['tipo']] ?? null;
        if (!$modelClass) { return back()->with('error', 'Tipo de ítem no válido.'); }
        $item = $modelClass::find($validated['id']);
        if (!$item) { return back()->with('error', 'El ítem no fue encontrado.'); }

        $revisionHoy = RevisionDiaria::where('user_id', $user->id)->where('fecha_revision', $hoy)->where('revisable_id', $item->id)->where('revisable_type', $modelClass)->first();
        if ($revisionHoy) $revisionHoy->delete();
        else RevisionDiaria::create(['user_id' => $user->id, 'fecha_revision' => $hoy, 'revisable_id' => $item->id, 'revisable_type' => $modelClass]);

        $onlyKey = strtolower(str_replace('ProcesoRadicado', 'radicado', $validated['tipo'])) . 's';
        $pageName = $onlyKey . '_page';

        return back(303)->with('success', 'Estado de revisión actualizado.')->withInput([$pageName => $request->input($pageName, 1)]);
    }

    public function exportPendientesExcel(Request $request)
    {
         // ... (Inicio del método igual) ...
        $user = Auth::user();
        $hoy = Carbon::today();

        $filters = $request->validate([
            'search_casos' => 'nullable|string', 'search_radicados' => 'nullable|string', 'search_contratos' => 'nullable|string',
            'start_date' => 'nullable|date_format:Y-m-d', 'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'active_tab' => 'required|in:casos,radicados,contratos',
            'abogado_id' => 'nullable|integer|exists:users,id', 
        ]);
        $activeTab = $filters['active_tab'];
        
        $baseQuery = null; $revisadosHoyIds = collect(); $modelClass = null; $idColumn = 'id';
        $relationsToLoad = [];
        switch ($activeTab) {
            case 'casos':
                $relationsToLoad = [
                    'deudor', 'cooperativa', 'user',
                    'revisionesDiarias' => fn($q) => $q->where('user_id', $user->id)->latest('fecha_revision')
                ];
                $baseQuery = Caso::query()->with($relationsToLoad);
                $revisadosHoyIds = RevisionDiaria::where('user_id', $user->id)->where('fecha_revision', $hoy)->where('revisable_type', Caso::class)->pluck('revisable_id');
                $modelClass = Caso::class; $idColumn = 'casos.id'; break;
            case 'radicados':
                $relationsToLoad = [
                    // CORRECCIÓN AQUÍ TAMBIÉN PARA EL EXCEL
                    'demandantes', 'demandados', 
                    'abogado', 
                    'revisionesDiarias' => fn($q) => $q->where('user_id', $user->id)->latest('fecha_revision')
                ];
                $baseQuery = ProcesoRadicado::query()->with($relationsToLoad);
                $revisadosHoyIds = RevisionDiaria::where('user_id', $user->id)->where('fecha_revision', $hoy)->where('revisable_type', ProcesoRadicado::class)->pluck('revisable_id');
                $modelClass = ProcesoRadicado::class; $idColumn = 'proceso_radicados.id'; break;
            case 'contratos':
                // ... (Igual que antes) ...
                $relationsToLoad = [
                    'cliente',
                    'proceso.abogado', 
                    'revisionesDiarias' => fn($q) => $q->where('user_id', $user->id)->latest('fecha_revision')
                ];
                $baseQuery = Contrato::query()->with($relationsToLoad)->where('estado', 'ACTIVO');
                $revisadosHoyIds = RevisionDiaria::where('user_id', $user->id)->where('fecha_revision', $hoy)->where('revisable_type', Contrato::class)->pluck('revisable_id');
                $modelClass = Contrato::class; $idColumn = 'contratos.id'; break;
            default: abort(400, 'Pestaña activa no válida.');
        }

        // ... (Resto del método export y getApplyFiltersClosure igual) ...
        $applyFilters = $this->getApplyFiltersClosure();
        $applyFilters($baseQuery, $activeTab, $filters);

        $queryPendientes = $baseQuery->whereNotIn($idColumn, $revisadosHoyIds);

        switch ($activeTab) {
            case 'casos': $queryPendientes->orderBy('fecha_vencimiento', 'desc'); break;
            case 'radicados': $queryPendientes->orderBy('fecha_proxima_revision', 'desc'); break;
            case 'contratos': $queryPendientes->orderBy('created_at', 'desc'); break;
        }

        $fileName = "pendientes_revision_{$activeTab}_" . $hoy->format('Ymd') . ".xlsx";
        return Excel::download(new PendientesRevisionExport($queryPendientes, $activeTab), $fileName);
    }
    
    private function getApplyFiltersClosure(): \Closure
    {
        // ... (Tu código original de getApplyFiltersClosure está bien, no lo toques) ...
        return function (Builder $query, string $type, array $filters) {
            $query->where(function (Builder $mainQuery) use ($type, $filters) {
                // 1. Filtro de Texto
                $searchKey = "search_{$type}";
                $searchTerm = $filters[$searchKey] ?? null;
                if ($searchTerm) {
                    $lowerSearch = strtolower($searchTerm);
                    $mainQuery->where(function (Builder $q) use ($lowerSearch, $searchTerm, $type) {
                         if ($type === 'casos') {
                             // ...
                             $q->whereRaw('LOWER(referencia_credito) LIKE ?', ["%{$lowerSearch}%"])
                                ->orWhereHas('deudor', fn($sq) => $sq->whereRaw('LOWER(nombre_completo) LIKE ?', ["%{$lowerSearch}%"])->orWhere('numero_documento', 'like', "%{$lowerSearch}%"))
                                ->orWhereHas('cooperativa', fn($sq) => $sq->whereRaw('LOWER(nombre) LIKE ?', ["%{$lowerSearch}%"]));
                        } elseif ($type === 'radicados') {
                            // CORREGIR AQUÍ TAMBIÉN SI USAS WHEREHAS:
                            // ProcesoRadicado tiene relación 'demandantes' y 'demandados' (plural).
                            // Laravel es inteligente y a veces resuelve 'demandante' singular si es belongsTo, pero aquí es belongsToMany
                             $q->whereRaw('LOWER(radicado) LIKE ?', ["%{$lowerSearch}%"])
                                ->orWhereHas('demandantes', fn($sq) => $sq->whereRaw('LOWER(nombre_completo) LIKE ?', ["%{$lowerSearch}%"])->orWhere('numero_documento', 'like', "%{$lowerSearch}%"))
                                ->orWhereHas('demandados', fn($sq) => $sq->whereRaw('LOWER(nombre_completo) LIKE ?', ["%{$lowerSearch}%"])->orWhere('numero_documento', 'like', "%{$lowerSearch}%"));
                        } elseif ($type === 'contratos') {
                             if (is_numeric($searchTerm)) $q->where('id', $searchTerm);
                            $q->orWhereHas('cliente', fn($sq) => $sq->whereRaw('LOWER(nombre_completo) LIKE ?', ["%{$lowerSearch}%"])->orWhere('numero_documento', 'like', "%{$lowerSearch}%"));
                        }
                    });
                }
                // ... (Filtros fecha y abogado quedan igual) ...
                 // 2. Filtro de Fecha
                $startDate = null; if (!empty($filters['start_date'])) try { $startDate = Carbon::parse($filters['start_date'])->startOfDay(); } catch (\Exception $e) {}
                $endDate = null; if (!empty($filters['end_date'])) try { $endDate = Carbon::parse($filters['end_date'])->endOfDay(); } catch (\Exception $e) {}
                $dateColumn = match ($type) { 'casos' => 'fecha_vencimiento', 'radicados' => 'fecha_proxima_revision', 'contratos' => 'created_at', default => null };
                if ($dateColumn && ($startDate || $endDate)) {
                    if ($startDate && $endDate) $mainQuery->whereBetween($dateColumn, [$startDate, $endDate]);
                    elseif ($startDate) $mainQuery->where($dateColumn, '>=', $startDate);
                    elseif ($endDate) $mainQuery->where($dateColumn, '<=', $endDate);
                }

                $abogadoId = $filters['abogado_id'] ?? null;
                if ($abogadoId) {
                    if ($type === 'casos') { $mainQuery->where('user_id', $abogadoId); } 
                    elseif ($type === 'radicados') { $mainQuery->where('abogado_id', $abogadoId); } 
                    elseif ($type === 'contratos') { $mainQuery->whereHas('proceso', function (Builder $procesoQuery) use ($abogadoId) { $procesoQuery->where('abogado_id', $abogadoId); }); }
                }
            });
        };
    }
}