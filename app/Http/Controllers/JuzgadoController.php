<?php

namespace App\Http\Controllers;

use App\Models\Juzgado;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
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
        if (!Auth::user()->isAdmin()) {
            abort(403);
        }
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
        if (!Auth::user()->isAdmin()) {
            abort(403);
        }
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
        if (!Auth::user()->isAdmin()) {
            abort(403);
        }
        $juzgado->delete();
        return redirect()->back()->with('success', 'Juzgado eliminado correctamente.');
    }

    // --- API DE BÚSQUEDA ---
    public function search(Request $request)
    {
        $term = $request->input('term', '');
        
        $query = Juzgado::query();

        if (!empty($term)) {
            $words = explode(' ', $term);
            foreach ($words as $word) {
                if (empty(trim($word))) continue;
                $normalized = $this->normalizeTerm(trim($word));
                $query->where(function($q) use ($word, $normalized) {
                    $q->where('nombre', 'ILIKE', '%' . $word . '%')
                      ->orWhere('municipio', 'ILIKE', '%' . $word . '%')
                      ->orWhere('departamento', 'ILIKE', '%' . $word . '%')
                      ->orWhere('distrito', 'ILIKE', '%' . $word . '%')
                      ->orWhereRaw("TRANSLATE(nombre, 'áéíóúüÁÉÍÓÚÜ', 'aeiouuAEIOUU') ILIKE ?", ["%{$normalized}%"])
                      ->orWhereRaw("TRANSLATE(municipio, 'áéíóúüÁÉÍÓÚÜ', 'aeiouuAEIOUU') ILIKE ?", ["%{$normalized}%"]);
                });
            }
        }
        
        $juzgados = $query->orderBy('nombre')
            ->limit(100)
            ->get();

        return response()->json($juzgados);
    }

    private function normalizeTerm($term)
    {
        return str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ü', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ü'],
            ['a', 'e', 'i', 'o', 'u', 'u', 'A', 'E', 'I', 'O', 'U', 'U'],
            $term
        );
    }
}