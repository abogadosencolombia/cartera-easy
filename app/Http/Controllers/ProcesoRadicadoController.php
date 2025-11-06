<?php

namespace App\Http\Controllers;

use App\Models\ProcesoRadicado;
use App\Http\Requests\StoreProcesoRadicadoRequest;
use App\Http\Requests\UpdateProcesoRadicadoRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

// Importación de Excel (ajusta la clase si tu import se llama distinto)
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ProcesosImport;
use Maatwebsite\Excel\Validators\ValidationException;

// --- INICIO: Añadidos para Actuaciones ---
use App\Models\Actuacion;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon; // <-- Añadido para formatear la fecha
// --- FIN: Añadidos para Actuaciones ---

class ProcesoRadicadoController extends Controller
{
    /**
     * Lista de procesos con filtros y paginación.
     */
    public function index(Request $request): Response
    {
        $query = ProcesoRadicado::with([
            'abogado',
            'responsableRevision',
            'juzgado',
            'tipoProceso',
            'demandante',
            'demandado',
        ]);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('radicado', 'ilike', "%{$search}%")
                    ->orWhere('asunto', 'ilike', "%{$search}%")
                    ->orWhereHas('demandante', fn($sq) => $sq->where('nombre_completo', 'Ilike', "%{$search}%"))
                    ->orWhereHas('demandado', fn($sq) => $sq->where('nombre_completo', 'Ilike', "%{$search}%"));
            });
        }

        // Añadimos el filtro por estado del proceso
        if ($request->filled('estado') && in_array($request->input('estado'), ['ACTIVO', 'CERRADO'])) {
            $query->where('estado', $request->input('estado'));
        }

        if ($desde = $request->date('rev_desde')) {
            $query->whereDate('fecha_proxima_revision', '>=', $desde);
        }
        if ($hasta = $request->date('rev_hasta')) {
            $query->whereDate('fecha_proxima_revision', '<=', $hasta);
        }

        $abogadoIds = ProcesoRadicado::query()
            ->whereNotNull('abogado_id')
            ->distinct()
            ->pluck('abogado_id');

        $abogadosParaFiltro = User::query()
            ->whereIn('id', $abogadoIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Radicados/Index', [
            'procesos' => $query->orderByDesc('fecha_proxima_revision')
                                    ->orderBy('radicado')
                                    ->paginate(15)
                                    ->withQueryString(),
            'filtros'  => $request->only(['search', 'rev_desde', 'rev_hasta', 'estado']),
            'abogados' => $abogadosParaFiltro,
        ]);
    }

    /**
     * Formulario de creación.
     */
    public function create(): Response
    {
        return Inertia::render('Radicados/Create');
    }

    /**
     * Crear proceso.
     */
    public function store(StoreProcesoRadicadoRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        // Aseguramos que los nuevos procesos se creen como ACTIVOS por defecto
        $data['estado'] = 'ACTIVO';

        $proceso = ProcesoRadicado::create($data);

        return to_route('procesos.edit', $proceso)
            ->with('success', 'Radicado creado exitosamente.');
    }

    /**
     * Mostrar detalle del proceso.
     */
    public function show(ProcesoRadicado $proceso): Response
    {
        $proceso->load([
            'abogado',
            'responsableRevision',
            'juzgado',
            'tipoProceso',
            'demandante', // Necesario para el botón "Generar Contrato"
            'demandado',
            'documentos' => fn($q) => $q->latest('created_at'),
            'actuaciones' => function ($query) {
                $query->with('user:id,name')->orderBy('fecha_actuacion', 'desc')->orderBy('created_at', 'desc');
            },
            // ===== MODIFICACIÓN AQUÍ =====
            // Cargar la relación 'contrato' con su ID y la foreign key
            // Seleccionamos solo las columnas necesarias para evitar cargar datos innecesarios.
            'contrato:id,proceso_id'
            // =============================
        ]);

        // Pasamos el objeto $proceso completo a la vista.
        // Incluirá 'actuaciones' y 'contrato' (si existe) debido al ->load() anterior.
        return Inertia::render('Radicados/Show', [
            'proceso' => $proceso,
            // 'actuaciones' => $proceso->actuaciones, // <-- Ya no es estrictamente necesario pasarlo por separado
        ]);
    }

    /**
     * Formulario de edición.
     */
    public function edit(ProcesoRadicado $proceso): Response
    {
        $proceso->load([
            'abogado',
            'responsableRevision',
            'juzgado',
            'tipoProceso',
            'demandante',
            'demandado',
        ]);

        return Inertia::render('Radicados/Edit', [
            'proceso' => $proceso,
        ]);
    }

    /**
     * Actualizar proceso.
     */
    public function update(UpdateProcesoRadicadoRequest $request, ProcesoRadicado $proceso)
    {
        $data = $request->validated();
        $proceso->update($data);

        return to_route('procesos.show', $proceso->id)
            ->with('success', 'Radicado actualizado correctamente.');
    }

    /**
     * Eliminar proceso.
     */
    public function destroy(ProcesoRadicado $proceso)
    {
        // Considerar borrar el contrato asociado si existe y la lógica de negocio lo requiere
        // if ($proceso->contrato) {
        //     $proceso->contrato->delete(); // ¡Cuidado! Esto borraría el contrato también.
        // }

        $proceso->actuaciones()->delete(); // Borrar actuaciones relacionadas
        $proceso->documentos()->delete(); // Borrar documentos relacionados (si aplica)

        $proceso->delete();

        return to_route('procesos.index')
            ->with('success', 'Radicado eliminado.');
    }

    /**
     * Vista del importador.
     */
    public function showImportForm(): Response
    {
        return Inertia::render('Radicados/Import');
    }

    /**
     * Manejar importación desde Excel.
     */
    public function handleImport(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        try {
            Excel::import(new ProcesosImport, $request->file('file'));
        } catch (ValidationException $e) {
            $failures = $e->failures();
            $errors = [];
            foreach ($failures as $failure) {
                $errors[] = 'Fila '.$failure->row().': '.implode(', ', $failure->errors());
            }
            return back()->withErrors(['file' => implode(' | ', $errors)]);
        } catch (\Exception $e) {
            return back()->withErrors(['file' => 'Error inesperado: '.$e->getMessage()]);
        }

        return to_route('procesos.index')->with('success', 'Archivo importado correctamente.');
    }

    /**
     * Cerrar un proceso.
     */
    public function close(Request $request, ProcesoRadicado $proceso)
    {
        $request->validate([
            'nota_cierre' => ['required', 'string', 'max:5000'],
        ]);

        $proceso->update([
            'estado' => 'CERRADO',
            'nota_cierre' => $request->input('nota_cierre'),
        ]);

        return back()->with('success', 'El caso ha sido cerrado.');
    }

    /**
     * Reabrir un proceso cerrado.
     */
    public function reopen(ProcesoRadicado $proceso)
    {
        $proceso->update([
            'estado' => 'ACTIVO',
            'nota_cierre' => null,
        ]);

        return back()->with('success', 'El caso ha sido reabierto exitosamente.');
    }

    // --- INICIO: Métodos CRUD para Actuaciones de Radicados ---

    /**
     * Guarda una nueva actuación manual para un radicado.
     */
    public function storeActuacion(Request $request, ProcesoRadicado $proceso)
    {
        $validated = $request->validate([
            'nota' => ['required', 'string', 'max:5000'],
            'fecha_actuacion' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $actuacion = $proceso->actuaciones()->create([
            'nota' => $validated['nota'],
            'fecha_actuacion' => $validated['fecha_actuacion'],
            'user_id' => Auth::id(),
        ]);

        // Actualizar ultima_actuacion
        $this->actualizarUltimaActuacion($proceso);

        return back()->with('success', 'Actuación registrada.');
    }

    /**
     * Actualiza una actuación específica.
     */
    public function updateActuacion(Request $request, Actuacion $actuacion)
    {
        $user = Auth::user();
        // Verificar si la actuación pertenece a un ProcesoRadicado y si el usuario tiene permisos
        if ($actuacion->actuable_type !== ProcesoRadicado::class || !$user || !in_array($user->tipo_usuario, ['admin', 'gestor', 'abogado'])) {
             abort(403, 'No autorizado para editar esta actuación.');
        }

        $validated = $request->validate([
            'nota' => ['required', 'string', 'max:5000'],
            'fecha_actuacion' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $actuacion->update($validated);

        // Actualizar ultima_actuacion del radicado asociado
        if ($actuacion->actuable instanceof ProcesoRadicado) {
            $this->actualizarUltimaActuacion($actuacion->actuable);
        }


        return back(303)->with('success', 'Actuación actualizada.');
    }

    /**
     * Elimina una actuación específica.
     */
    public function destroyActuacion(Actuacion $actuacion)
    {
        $user = Auth::user();
         // Verificar si la actuación pertenece a un ProcesoRadicado y si el usuario tiene permisos
        if ($actuacion->actuable_type !== ProcesoRadicado::class || !$user || !in_array($user->tipo_usuario, ['admin', 'gestor', 'abogado'])) {
             abort(403, 'No autorizado para eliminar esta actuación.');
        }

        $proceso = $actuacion->actuable; // Guardar referencia antes de borrar
        $actuacion->delete();

        // Actualizar ultima_actuacion del radicado asociado (si aún existe)
        if ($proceso instanceof ProcesoRadicado) {
             $this->actualizarUltimaActuacion($proceso);
        }

        return back(303)->with('success', 'Actuación eliminada.');
    }

    /**
     * Función helper para actualizar el campo 'ultima_actuacion' del radicado.
     */
    private function actualizarUltimaActuacion(ProcesoRadicado $proceso)
    {
        if (!$proceso) return;

        // Forzar recarga de actuaciones para obtener la más reciente después de cambios
        $proceso->load('actuaciones');

        // Encontrar la fecha de actuación más reciente para este proceso
        $fechaMasReciente = $proceso->actuaciones()->max('fecha_actuacion');

        // Formatea la fecha a texto o déjala null si no hay actuaciones
        $textoUltimaActuacion = $fechaMasReciente
            ? Carbon::parse($fechaMasReciente)->isoFormat('DD [de] MMMM [de] YYYY') // Formato "24 de octubre de 2025"
            : null; // Si no hay actuaciones, se limpia el campo

        // Actualiza el campo en el radicado SIN disparar eventos para evitar bucles si hay observers
        $proceso->updateQuietly([
            'ultima_actuacion' => $textoUltimaActuacion
        ]);
    }
    // --- FIN: Métodos CRUD para Actuaciones de Radicados ---
}

