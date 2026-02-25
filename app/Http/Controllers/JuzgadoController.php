<?php

namespace App\Http\Controllers;

use App\Models\Juzgado;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Imports\JuzgadosImport; 
use Maatwebsite\Excel\Facades\Excel; 

class JuzgadoController extends Controller
{
    /**
     * Muestra el listado con BUSCADOR INTELIGENTE.
     */
    public function index(Request $request)
    {
        $query = Juzgado::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'ilike', "%{$search}%")
                  ->orWhere('municipio', 'ilike', "%{$search}%")
                  ->orWhere('departamento', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $juzgados = $query->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Juzgados/Index', [
            'juzgados' => $juzgados,
            'filters' => $request->only(['search']),
        ]);
    }

    // --- IMPORTACIÓN ---

    public function importForm()
    {
        return Inertia::render('Juzgados/Import');
    }

    public function import(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        try {
            Excel::import(new JuzgadosImport, $request->file('file'));
            return redirect()->route('juzgados.index')
                ->with('success', '¡Importación completada con éxito!');
        } catch (\Exception $e) {
            return back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    // --- CREAR ---

    public function create()
    {
        return Inertia::render('Juzgados/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'telefono' => 'nullable|string|max:100',
            'municipio' => 'nullable|string|max:100',
            'departamento' => 'nullable|string|max:100',
            'distrito' => 'nullable|string|max:100',
        ]);

        Juzgado::create($validated);

        return redirect()->route('juzgados.index')
            ->with('success', 'Juzgado creado exitosamente.');
    }

    // --- EDITAR Y ELIMINAR ---

    public function edit(Juzgado $juzgado)
    {
        return Inertia::render('Juzgados/Edit', [
            'juzgado' => $juzgado
        ]);
    }

    public function update(Request $request, Juzgado $juzgado)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'telefono' => 'nullable|string|max:100',
            'municipio' => 'nullable|string|max:100',
            'departamento' => 'nullable|string|max:100',
            'distrito' => 'nullable|string|max:100',
        ]);

        $juzgado->update($validated);

        return redirect()->route('juzgados.index')
            ->with('success', 'Juzgado actualizado correctamente.');
    }

    public function destroy(Juzgado $juzgado)
    {
        $juzgado->delete();
        return redirect()->back()->with('success', 'Juzgado eliminado correctamente.');
    }

    // --- API DE BÚSQUEDA ---
    public function search(Request $request)
    {
        $term = $request->input('term', '');
        if (empty($term)) return response()->json(Juzgado::limit(20)->get());
        
        $juzgados = Juzgado::where('nombre', 'ILIKE', '%' . $term . '%')
            ->limit(50)
            ->get();

        return response()->json($juzgados);
    }
}