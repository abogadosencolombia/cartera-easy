<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Caso;
use App\Models\ProcesoRadicado;
use App\Models\Contrato;
use App\Models\RevisionDiaria;
use App\Models\User; // <-- AÑADIDO: Importar el modelo User
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder; // Importar Builder
use Illuminate\Support\Facades\Log;
// --- INICIO: AÑADIR IMPORTS PARA EXCEL ---
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PendientesRevisionExport;
// --- FIN: AÑADIR IMPORTS PARA EXCEL ---


class RevisionDiariaController extends Controller
{
    /**
     * Muestra la página principal de revisiones diarias.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $hoy = Carbon::today();

        // --- INICIO: Cargar Abogados (CORREGIDO) ---
        // Se corrigió para jalar solo 'id' y 'name', ya que 'nombre_completo' no existe en 'users'
        $abogados = User::orderBy('name')->get(['id', 'name']);
        // --- FIN: Cargar Abogados ---


        // --- INICIO: Obtener y Validar Filtros ---
        $filters = $request->only(
            'search_casos', 'search_radicados', 'search_contratos',
            'start_date', 'end_date',
            'abogado_id' // <-- AÑADIDO: Nuevo filtro
        );

        Log::debug('RevisionDiariaController: Filtros recibidos', $filters);
        // --- FIN: Obtener y Validar Filtros ---


        // --- INICIO: Función Auxiliar Genérica para Filtros (Refactorizada) ---
        // Se usa la función centralizada getApplyFiltersClosure()
        // para asegurar que la vista y el export usen la misma lógica.
        $applyFilters = $this->getApplyFiltersClosure();
        // --- FIN: Función Auxiliar Genérica para Filtros ---

        // --- INICIO: Construir Consultas Base ---
        $casosQuery = Caso::query()->with(['deudor', 'cooperativa']);
        $radicadosQuery = ProcesoRadicado::query()->with(['demandante', 'demandado']);
        $contratosQuery = Contrato::query()->with(['cliente'])->where('estado', 'ACTIVO');
        // --- FIN: Construir Consultas Base ---

        // --- Aplicar filtros a las consultas ---
        $applyFilters($casosQuery, 'casos', $filters);
        $applyFilters($radicadosQuery, 'radicados', $filters);
        $applyFilters($contratosQuery, 'contratos', $filters);

        // --- INICIO: Calcular conteos de pendientes (BASADO EN FILTROS) ---
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
        Log::debug('Conteos de pendientes calculados', $pendientesCounts);
        // --- FIN: Calcular conteos de pendientes ---


        // --- INICIO: Paginar Resultados ---
        $casos = (clone $baseFilteredCasosQuery)->orderBy('fecha_vencimiento', 'desc')->paginate(25, ['*'], 'casos_page')->appends($request->all());
        $radicados = (clone $baseFilteredRadicadosQuery)->orderBy('fecha_proxima_revision', 'desc')->paginate(25, ['*'], 'radicados_page')->appends($request->all());
        $contratos = (clone $baseFilteredContratosQuery)->orderBy('created_at', 'desc')->paginate(25, ['*'], 'contratos_page')->appends($request->all());
        // --- FIN: Paginar Resultados ---


        // --- INICIO: Transformar Colecciones (Añadir estado 'revisadoHoy') ---
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
        // --- FIN: Transformar Colecciones ---


        // --- INICIO: Renderizar Vista ---
        return Inertia::render('Revisiones/Index', [
            'casos' => $casos,
            'radicados' => $radicados,
            'contratos' => $contratos,
            'pendientesCounts' => $pendientesCounts,
            'filters' => $filters,
            'abogados' => $abogados, // <-- AÑADIDO: Pasar abogados a la vista
        ]);
    }

    /**
     * Marca o desmarca un ítem como revisado.
     */
    public function toggle(Request $request)
    {
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

    // --- MÉTODO EXPORTAR PENDIENTES ---
    /**
     * Exporta a Excel los ítems pendientes de revisión según los filtros.
     */
    public function exportPendientesExcel(Request $request)
    {
        $user = Auth::user();
        $hoy = Carbon::today();

        $filters = $request->validate([
            'search_casos' => 'nullable|string', 'search_radicados' => 'nullable|string', 'search_contratos' => 'nullable|string',
            'start_date' => 'nullable|date_format:Y-m-d', 'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'active_tab' => 'required|in:casos,radicados,contratos',
            'abogado_id' => 'nullable|integer|exists:users,id', // <-- AÑADIDO: Validar el nuevo filtro
        ]);
        $activeTab = $filters['active_tab'];
        Log::debug('Exportando pendientes para:', ['tab' => $activeTab, 'filters' => $filters]);

        $baseQuery = null; $revisadosHoyIds = collect(); $modelClass = null; $idColumn = 'id';
        // --- INICIO: Carga ansiosa para exportación ---
        $relationsToLoad = [];
        switch ($activeTab) {
            case 'casos':
                // --- INICIO: OPTIMIZACIÓN ---
                $relationsToLoad = [
                    'deudor', 'cooperativa',
                    'user', // <-- AÑADIDO: Carga el abogado/usuario responsable
                    'revisionesDiarias' => fn($q) => $q->where('user_id', $user->id)->latest('fecha_revision')
                ];
                // --- FIN: OPTIMIZACIÓN ---
                $baseQuery = Caso::query()->with($relationsToLoad);
                $revisadosHoyIds = RevisionDiaria::where('user_id', $user->id)->where('fecha_revision', $hoy)->where('revisable_type', Caso::class)->pluck('revisable_id');
                $modelClass = Caso::class; $idColumn = 'casos.id'; break;
            case 'radicados':
                // --- INICIO: OPTIMIZACIÓN ---
                $relationsToLoad = [
                    'demandante', 'demandado',
                    'abogado', // <-- AÑADIDO: Carga el abogado responsable
                    'revisionesDiarias' => fn($q) => $q->where('user_id', $user->id)->latest('fecha_revision')
                ];
                // --- FIN: OPTIMIZACIÓN ---
                $baseQuery = ProcesoRadicado::query()->with($relationsToLoad);
                $revisadosHoyIds = RevisionDiaria::where('user_id', $user->id)->where('fecha_revision', $hoy)->where('revisable_type', ProcesoRadicado::class)->pluck('revisable_id');
                $modelClass = ProcesoRadicado::class; $idColumn = 'proceso_radicados.id'; break;
            case 'contratos':
                // --- INICIO: OPTIMIZACIÓN ---
                $relationsToLoad = [
                    'cliente',
                    'proceso.abogado', // <-- AÑADIDO: Carga el abogado a través del proceso
                    'revisionesDiarias' => fn($q) => $q->where('user_id', $user->id)->latest('fecha_revision')
                ];
                // --- FIN: OPTIMIZACIÓN ---
                $baseQuery = Contrato::query()->with($relationsToLoad)->where('estado', 'ACTIVO');
                $revisadosHoyIds = RevisionDiaria::where('user_id', $user->id)->where('fecha_revision', $hoy)->where('revisable_type', Contrato::class)->pluck('revisable_id');
                $modelClass = Contrato::class; $idColumn = 'contratos.id'; break;
            default: abort(400, 'Pestaña activa no válida.');
        }
        // --- FIN: Carga ansiosa para exportación ---

        // Se usa la función centralizada
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

    /**
     * Función centralizada para aplicar todos los filtros de búsqueda.
     * Usada por index() y exportPendientesExcel()
     */
    private function getApplyFiltersClosure(): \Closure
    {
         return function (Builder $query, string $type, array $filters) {
            $query->where(function (Builder $mainQuery) use ($type, $filters) {

                // 1. Filtro de Texto
                $searchKey = "search_{$type}";
                $searchTerm = $filters[$searchKey] ?? null;
                if ($searchTerm) {
                    $lowerSearch = strtolower($searchTerm);
                    $mainQuery->where(function (Builder $q) use ($lowerSearch, $searchTerm, $type) {
                         if ($type === 'casos') {
                            $q->whereRaw('LOWER(referencia_credito) LIKE ?', ["%{$lowerSearch}%"])
                                ->orWhereHas('deudor', fn($sq) => $sq->whereRaw('LOWER(nombre_completo) LIKE ?', ["%{$lowerSearch}%"])->orWhere('numero_documento', 'like', "%{$lowerSearch}%"))
                                ->orWhereHas('cooperativa', fn($sq) => $sq->whereRaw('LOWER(nombre) LIKE ?', ["%{$lowerSearch}%"]));
                        } elseif ($type === 'radicados') {
                             $q->whereRaw('LOWER(radicado) LIKE ?', ["%{$lowerSearch}%"])
                                ->orWhereHas('demandante', fn($sq) => $sq->whereRaw('LOWER(nombre_completo) LIKE ?', ["%{$lowerSearch}%"])->orWhere('numero_documento', 'like', "%{$lowerSearch}%"))
                                ->orWhereHas('demandado', fn($sq) => $sq->whereRaw('LOWER(nombre_completo) LIKE ?', ["%{$lowerSearch}%"])->orWhere('numero_documento', 'like', "%{$lowerSearch}%"));
                        } elseif ($type === 'contratos') {
                            if (is_numeric($searchTerm)) $q->where('id', $searchTerm);
                            $q->orWhereHas('cliente', fn($sq) => $sq->whereRaw('LOWER(nombre_completo) LIKE ?', ["%{$lowerSearch}%"])->orWhere('numero_documento', 'like', "%{$lowerSearch}%"));
                        }
                    });
                }

                // 2. Filtro de Fecha
                $startDate = null; if (!empty($filters['start_date'])) try { $startDate = Carbon::parse($filters['start_date'])->startOfDay(); } catch (\Exception $e) {}
                $endDate = null; if (!empty($filters['end_date'])) try { $endDate = Carbon::parse($filters['end_date'])->endOfDay(); } catch (\Exception $e) {}
                $dateColumn = match ($type) { 'casos' => 'fecha_vencimiento', 'radicados' => 'fecha_proxima_revision', 'contratos' => 'created_at', default => null };
                if ($dateColumn && ($startDate || $endDate)) {
                    if ($startDate && $endDate) $mainQuery->whereBetween($dateColumn, [$startDate, $endDate]);
                    elseif ($startDate) $mainQuery->where($dateColumn, '>=', $startDate);
                    elseif ($endDate) $mainQuery->where($dateColumn, '<=', $endDate);
                }

                // --- 3. INICIO: Filtro de Abogado Responsable ---
                $abogadoId = $filters['abogado_id'] ?? null;
                
                if ($abogadoId) {
                    Log::debug("Aplicando filtro de Abogado ID [$abogadoId] para [$type]");
                    
                    if ($type === 'casos') {
                        // Asumido por el export: Caso tiene 'user_id'
                        $mainQuery->where('user_id', $abogadoId);
                    } 
                    elseif ($type === 'radicados') {
                        // Asumido por el export: ProcesoRadicado tiene 'abogado_id'
                        $mainQuery->where('abogado_id', $abogadoId);
                    } 
                    elseif ($type === 'contratos') {
                        // Asumido por el export: Contrato -> Proceso -> Abogado
                        $mainQuery->whereHas('proceso', function (Builder $procesoQuery) use ($abogadoId) {
                            $procesoQuery->where('abogado_id', $abogadoId);
                        });
                    }
                }
                // --- FIN: Filtro de Abogado Responsable ---

            });
        };
    }
}

