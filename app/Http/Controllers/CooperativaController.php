<?php

namespace App\Http\Controllers;

use App\Models\Cooperativa;
use App\Http\Requests\StoreCooperativaRequest;
use App\Http\Requests\UpdateCooperativaRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request; // Importamos Request
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CooperativaController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource with search and pagination.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Cooperativa::class);
        $user = Auth::user();

        // Iniciamos la consulta base dependiendo del tipo de usuario
        $query = ($user->tipo_usuario === 'admin')
            ? Cooperativa::query()
            : $user->cooperativas()->getQuery();

        // Aplicamos el filtro de búsqueda si existe
        $query->when($request->input('search'), function ($query, $search) {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('NIT', 'like', "%{$search}%")
                  ->orWhere('representante_legal_nombre', 'like', "%{$search}%")
                  ->orWhere('contacto_correo', 'like', "%{$search}%");
            });
        });

        // Paginamos los resultados, 20 por página, y mantenemos los filtros en la URL
        $cooperativas = $query->paginate(20)->withQueryString();

        return Inertia::render('Cooperativas/Index', [
            'cooperativas' => $cooperativas,
            // Pasamos los filtros actuales a la vista para que el input de búsqueda los recuerde
            'filters' => $request->only(['search']),
            'can' => [
                'create_cooperativas' => $user->can('create', Cooperativa::class),
                'delete_cooperativas' => $user->can('delete', Cooperativa::class),
            ]
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Cooperativa::class);
        return Inertia::render('Cooperativas/Create');
    }

    public function store(StoreCooperativaRequest $request): RedirectResponse
    {
        $this->authorize('create', Cooperativa::class);
        Cooperativa::create($request->validated());
        return to_route('cooperativas.index')->with('success', '¡Cooperativa registrada exitosamente!');
    }

    public function show(Cooperativa $cooperativa): Response
    {
        $this->authorize('view', $cooperativa);
        $cooperativa->load('documentos');
        return Inertia::render('Cooperativas/Show', [
            'cooperativa' => $cooperativa,
            'can' => [
                'update' => Auth::user()->can('update', $cooperativa),
                'delete' => Auth::user()->can('delete', $cooperativa),
            ]
        ]);
    }

    public function edit(Cooperativa $cooperativa): Response
    {
        $this->authorize('update', $cooperativa);
        return Inertia::render('Cooperativas/Edit', [
            'cooperativa' => $cooperativa
        ]);
    }

    public function update(UpdateCooperativaRequest $request, Cooperativa $cooperativa): RedirectResponse
    {
        $this->authorize('update', $cooperativa);
        $cooperativa->update($request->validated());
        return to_route('cooperativas.index')->with('success', '¡Cooperativa actualizada exitosamente!');
    }

    public function destroy(Cooperativa $cooperativa): RedirectResponse
    {
        $this->authorize('delete', $cooperativa);
        $cooperativa->delete();
        return to_route('cooperativas.index')->with('success', '¡Cooperativa eliminada exitosamente!');
    }
}
