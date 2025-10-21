<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Http\Requests\StorePersonaRequest;
use App\Http\Requests\UpdatePersonaRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PersonaController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Persona::class);

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $validSortColumns = ['nombre_completo', 'created_at'];
        if (!in_array($sortBy, $validSortColumns)) {
            $sortBy = 'created_at';
        }

        $query = Persona::query();

        if ($request->input('status') === 'suspended') {
            $query->onlyTrashed();
        }

        $query->when($request->input('search'), function ($query, $search) {
            $query->where(function ($q) use ($search) {
                $q->where('nombre_completo', 'ilike', "%{$search}%")
                    ->orWhere('numero_documento', 'ilike', "%{$search}%")
                    ->orWhere('celular_1', 'ilike', "%{$search}%")
                    ->orWhere('correo_1', 'ilike', "%{$search}%")
                    ->orWhereRaw('CAST(id AS TEXT) Ilike ?', ["%{$search}%"]);
            });
        });

        // --- CORRECCIÓN AQUÍ: Eliminamos el ->with([...]) que causaba el error 500 ---
        $personas = $query->orderBy($sortBy, $sortDirection)
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Personas/Index', [
            'personas' => $personas,
            'filters' => $request->only('search', 'sort_by', 'sort_direction', 'status'),
            'can' => [
                'delete_personas' => Auth::user()->can('delete', Persona::class)
            ]
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Persona::class);
        return Inertia::render('Personas/Create');
    }

    public function store(StorePersonaRequest $request): RedirectResponse
    {
        $this->authorize('create', Persona::class);
        $data = $request->validated();

        if (isset($data['social_links'])) {
            $data['social_links'] = array_values(array_filter($data['social_links'], fn ($r) =>
                isset($r['url']) && trim((string)$r['url']) !== ''
            ));
        }
        
        if (isset($data['addresses'])) {
            $data['addresses'] = array_values(array_filter($data['addresses'], fn ($r) =>
                (isset($r['address']) && trim((string)$r['address']) !== '') || 
                (isset($r['city']) && trim((string)$r['city']) !== '')
            ));
        }

        Persona::create($data);
        return to_route('personas.index')->with('success', '¡Persona registrada exitosamente!');
    }

    public function show(Persona $persona)
    {
        $this->authorize('view', $persona);
        return redirect()->route('personas.edit', $persona);
    }

    public function edit(Persona $persona): Response
    {
        $this->authorize('update', $persona);
        return Inertia::render('Personas/Edit', ['persona' => $persona]);
    }

    public function update(UpdatePersonaRequest $request, Persona $persona): RedirectResponse
    {
        $this->authorize('update', $persona);
        $data = $request->validated();

        if (isset($data['social_links'])) {
            $data['social_links'] = array_values(array_filter($data['social_links'], fn ($r) =>
                isset($r['url']) && trim((string)$r['url']) !== ''
            ));
        }

        if (isset($data['addresses'])) {
            $data['addresses'] = array_values(array_filter($data['addresses'], fn ($r) =>
                (isset($r['address']) && trim((string)$r['address']) !== '') || 
                (isset($r['city']) && trim((string)$r['city']) !== '')
            ));
        }

        $persona->update($data);
        return to_route('personas.index')->with('success', '¡Datos de persona actualizados!');
    }

    public function destroy(Persona $persona): RedirectResponse
    {
        $this->authorize('delete', $persona);
        $persona->delete();
        
        return to_route('personas.index')->with('success', '¡Persona suspendida correctamente!');
    }

    public function restore($id): RedirectResponse
    {
        // Nota: La autorización para 'restore' ahora está en tu PersonaPolicy.
        $this->authorize('restore', Persona::class);
        
        $persona = Persona::onlyTrashed()->findOrFail($id);
        $persona->restore();

        return redirect()->back()->with('success', '¡Persona reactivada correctamente!');
    }
}

