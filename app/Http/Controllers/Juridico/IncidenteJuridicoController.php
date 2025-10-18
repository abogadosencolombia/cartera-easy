<?php

namespace App\Http\Controllers\Juridico;

use App\Http\Controllers\Controller;
use App\Models\IncidenteJuridico;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Exports\IncidentesExport; // <-- Añade este 'use'
use Maatwebsite\Excel\Facades\Excel; // <-- Y este también
use Barryvdh\DomPDF\Facade\Pdf; // <-- Y este para el PDF

class IncidenteJuridicoController extends Controller
{
     public function index(Request $request)
    {
        $query = IncidenteJuridico::with('responsable:id,name')->latest('fecha_registro');

        $query->when($request->input('search'), function ($query, $search) {
            $query->where('asunto', 'like', "%{$search}%");
        });

        $query->when($request->input('estado'), function ($query, $estado) {
            $query->where('estado', $estado);
        });

        $query->when($request->input('responsable_id'), function ($query, $responsable_id) {
            $query->where('usuario_responsable_id', $responsable_id);
        });

        return Inertia::render('Juridico/Incidentes/Index', [
            'incidentes' => $query->paginate(10)->withQueryString(),
            'filters' => $request->only(['search', 'estado', 'responsable_id']),
            'usuarios' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Juridico/Incidentes/Create', [
            'usuarios' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'asunto' => 'required|string|max:255',
            'descripcion' => 'required|string',
            'usuario_responsable_id' => 'required|exists:users,id',
            'origen' => 'required|in:manual,auditoria,validacion',
        ]);

        IncidenteJuridico::create($validated);

        return redirect()->route('admin.incidentes-juridicos.index')->with('success', 'Incidente registrado correctamente.');
    }

    /**
     * Muestra el panel de detalles de un incidente específico.
     * ASEGÚRATE DE QUE ESTA FUNCIÓN ESTÉ EXACTAMENTE ASÍ.
     * La variable debe ser (IncidenteJuridico $incidente)
     */
    public function show(IncidenteJuridico $incidente)
    {
        $incidente->load([
            'responsable:id,name',
            'tickets.creador:id,name',
            'tickets.asignado:id,name',
            'tickets.decision',
            'archivos'
        ]);

        return Inertia::render('Juridico/Incidentes/Show', [
            'incidente' => $incidente,
            'usuarios' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Elimina un incidente jurídico específico.
     */
    public function destroy(IncidenteJuridico $incidente)
    {
        $incidente->delete();

        return back()->with('success', 'Incidente eliminado con éxito.');
    }

    public function export(Request $request)
    {
        $filters = $request->only(['search', 'estado', 'responsable_id']);
        $format = $request->query('format', 'xlsx');

        // Lógica para Excel (sin cambios, ya es correcta)
        if ($format === 'xlsx') {
            return Excel::download(new IncidentesExport($filters), 'incidentes.xlsx');
        }

        // Lógica para PDF (VERSIÓN CORREGIDA)
        if ($format === 'pdf') {
            $incidentes = (new IncidentesExport($filters))->query()->get();
            $pdf = Pdf::loadView('pdf.incidentes', ['incidentes' => $incidentes]);

            // En lugar de devolverlo directamente, creamos una respuesta
            // y le añadimos los encabezados correctos a mano para máxima compatibilidad.
            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="incidentes.pdf"',
            ]);
        }

        return back()->with('error', 'Formato de exportación no válido.');
    }
}