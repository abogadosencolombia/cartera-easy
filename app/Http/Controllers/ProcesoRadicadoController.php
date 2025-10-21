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

        // --- INICIO DE LA MODIFICACIÓN ---
        // Añadimos el filtro por estado del proceso
        if ($request->filled('estado') && in_array($request->input('estado'), ['ACTIVO', 'CERRADO'])) {
            $query->where('estado', $request->input('estado'));
        }
        // --- FIN DE LA MODIFICACIÓN ---

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
            // Pasamos el nuevo filtro a la vista
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
        // --- INICIO DE LA MODIFICACIÓN ---
        // Aseguramos que los nuevos procesos se creen como ACTIVOS por defecto
        $data['estado'] = 'ACTIVO';
        // --- FIN DE LA MODIFICACIÓN ---

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
            'demandante',
            'demandado',
            'documentos' => fn($q) => $q->latest('created_at'),
        ]);

        return Inertia::render('Radicados/Show', [
            'proceso' => $proceso,
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
}

