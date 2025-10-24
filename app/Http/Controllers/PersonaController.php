<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Models\Cooperativa;
use App\Models\User;
use App\Http\Requests\StorePersonaRequest;
use App\Http\Requests\UpdatePersonaRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use App\Exports\PersonasExport;
use Maatwebsite\Excel\Facades\Excel;

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

        // --- AÑADIDO: Lógica para filtrar por Cooperativa ---
        $query->when($request->input('cooperativa_id'), function ($q, $cooperativaId) {
            $q->whereHas('cooperativas', fn($sq) => $sq->where('cooperativas.id', $cooperativaId));
        });

        // --- AÑADIDO: Lógica para filtrar por Abogado ---
        $query->when($request->input('abogado_id'), function ($q, $abogadoId) {
            $q->whereHas('abogados', fn($sq) => $sq->where('users.id', $abogadoId));
        });

        // Cargamos las relaciones de forma ligera
        $personas = $query->with([
            'cooperativas:id,nombre',
            'abogados:id,name'
        ])
            ->orderBy($sortBy, $sortDirection)
            ->paginate(20)
            ->withQueryString();

        // --- AÑADIDO: Cargar listas para los filtros ---
        $allCooperativas = Cooperativa::select('id', 'nombre')->orderBy('nombre')->get();
        $allAbogados = User::whereIn('tipo_usuario', ['abogado', 'gestor'])
                            ->select('id', 'name')
                            ->orderBy('name')
                            ->get();

        return Inertia::render('Personas/Index', [
            'personas' => $personas,
            // --- AÑADIDO: Enviar listas al frontend ---
            'allCooperativas' => $allCooperativas,
            'allAbogados' => $allAbogados,
            // --- MODIFICADO: Incluir nuevos filtros ---
            'filters' => $request->only('search', 'sort_by', 'sort_direction', 'status', 'cooperativa_id', 'abogado_id'),
            'can' => [
                'delete_personas' => Auth::user()->can('delete', Persona::class)
            ]
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Persona::class);

        // Obtenemos todas las cooperativas (solo ID y nombre)
        $allCooperativas = Cooperativa::select('id', 'nombre')->orderBy('nombre')->get();
        
        // Obtenemos todos los usuarios que sean 'abogado' o 'gestor'
        $allAbogados = User::whereIn('tipo_usuario', ['abogado', 'gestor'])
                            ->select('id', 'name')
                            ->orderBy('name')
                            ->get();

        return Inertia::render('Personas/Create', [
            // Enviamos las listas al formulario de Vue
            'allCooperativas' => $allCooperativas,
            'allAbogados' => $allAbogados,
        ]);
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

        $persona = Persona::create($data);

        if ($request->has('cooperativas_ids')) {
            $persona->cooperativas()->sync($request->input('cooperativas_ids'));
        }
        if ($request->has('abogados_ids')) {
            $persona->abogados()->sync($request->input('abogados_ids'));
        }

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

        $allCooperativas = Cooperativa::select('id', 'nombre')->orderBy('nombre')->get();
        $allAbogados = User::whereIn('tipo_usuario', ['abogado', 'gestor'])
                            ->select('id', 'name')
                            ->orderBy('name')
                            ->get();

        $persona->load('cooperativas:id', 'abogados:id');

        return Inertia::render('Personas/Edit', [
            'persona' => $persona,
            'allCooperativas' => $allCooperativas,
            'allAbogados' => $allAbogados,
        ]);
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

        $persona->cooperativas()->sync($request->input('cooperativas_ids'));
        $persona->abogados()->sync($request->input('abogados_ids'));

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
        $this->authorize('restore', Persona::class);
        
        $persona = Persona::onlyTrashed()->findOrFail($id);
        $persona->restore();

        return redirect()->back()->with('success', '¡Persona reactivada correctamente!');
    }

    /**
     * Exporta los datos de personas a un archivo Excel, aplicando los filtros actuales.
     */
    public function exportExcel(Request $request)
    {
        // 1. Autorización (Aseguramos que solo usuarios autorizados puedan exportar)
        //    Usamos 'viewAny' como permiso base, puedes ajustarlo si necesitas uno específico.
        $this->authorize('viewAny', Persona::class);

        // 2. Recogemos todos los filtros válidos de la URL (los mismos que usa index)
        $filters = $request->only(['search', 'status', 'cooperativa_id', 'abogado_id', 'sort_by', 'sort_direction']);

        // 3. Generamos un nombre de archivo dinámico
        $fileName = 'personas_' . date('Ymd_His') . '.xlsx';

        // 4. Usamos la clase PersonasExport (pasándole los filtros) para generar y descargar el Excel
        return Excel::download(new PersonasExport($filters), $fileName);
    }
}

