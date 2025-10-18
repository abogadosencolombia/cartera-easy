<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Cooperativa;
use App\Models\Persona;
use App\Models\Especialidad;
use App\Models\UserDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rules;
use Illuminate\Validation\Rules\File;

class UserController extends Controller
{
    use AuthorizesRequests;

    /**
     * Muestra una lista de todos los usuarios.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', User::class);
        
        return Inertia::render('Users/Index', [
            'users' => User::with('cooperativas', 'especialidades')->get(),
        ]);
    }

    /**
     * Muestra el formulario para crear un nuevo usuario.
     */
    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Users/Create', [
            'allCooperativas' => Cooperativa::all(['id', 'nombre']),
            'allEspecialidades' => Especialidad::all(['id', 'nombre']),
            'personas' => Persona::all(['id', 'nombre_completo', 'numero_documento']),
        ]);
    }

    /**
     * Almacena un nuevo usuario en la base de datos.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'tipo_usuario' => 'required|in:admin,gestor,abogado,cliente',
            'cooperativas' => 'nullable|array',
            'especialidades' => 'nullable|array',
            'persona_id' => 'nullable|exists:personas,id'
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'tipo_usuario' => $request->tipo_usuario,
            'persona_id' => $request->persona_id,
        ]);

        if ($request->has('cooperativas') && in_array($user->tipo_usuario, ['abogado', 'gestor', 'cliente'])) {
            $user->cooperativas()->sync($request->cooperativas);
        }

        if ($request->has('especialidades') && in_array($user->tipo_usuario, ['abogado', 'gestor'])) {
            $user->especialidades()->sync($request->especialidades);
        }

        return to_route('admin.users.index')->with('success', '¡Usuario creado exitosamente!');
    }
    
    /**
     * Muestra la vista de detalles de un usuario específico.
     */
    public function show(User $user): Response
    {
        $this->authorize('view', $user);

        // Carga todas las relaciones necesarias para la vista de detalles
        $user->load('cooperativas', 'especialidades', 'documents');

        return Inertia::render('Users/Show', [
            'user' => $user,
        ]);
    }

    /**
     * Muestra el formulario para editar un usuario existente.
     */
    public function edit(User $user): Response
    {
        $this->authorize('update', $user);
        
        return Inertia::render('Users/Edit', [
            'user' => $user->load('cooperativas', 'especialidades'),
            'allCooperativas' => Cooperativa::all(['id', 'nombre']),
            'allEspecialidades' => Especialidad::all(['id', 'nombre']),
            'personas' => Persona::all(['id', 'nombre_completo', 'numero_documento']),
        ]);
    }

    /**
     * Actualiza la información de un usuario en la base de datos.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'tipo_usuario' => 'required|in:admin,gestor,abogado,cliente',
            'password' => 'nullable|string|min:8|confirmed',
            'estado_activo' => 'required|boolean',
            'preferencias_notificacion.email' => 'required|boolean',
            'preferencias_notificacion.in-app' => 'required|boolean',
            'cooperativas' => 'nullable|array',
            'cooperativas.*' => 'exists:cooperativas,id',
            'especialidades' => 'nullable|array',
            'especialidades.*' => 'exists:especialidades,id',
            'persona_id' => 'nullable|exists:personas,id'
        ]);
        
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->tipo_usuario = $validated['tipo_usuario'];
        $user->estado_activo = $validated['estado_activo'];
        $user->persona_id = $validated['persona_id'] ?? null;
        $user->preferencias_notificacion = $validated['preferencias_notificacion'];

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        
        $user->save();
        
        if (in_array($user->tipo_usuario, ['abogado', 'gestor', 'cliente'])) {
             $user->cooperativas()->sync($request->input('cooperativas', []));
        } else {
             $user->cooperativas()->sync([]);
        }
        
        if (in_array($user->tipo_usuario, ['abogado', 'gestor'])) {
             $user->especialidades()->sync($request->input('especialidades', []));
        } else {
             $user->especialidades()->sync([]);
        }
        
        return to_route('admin.users.index')->with('success', 'Usuario actualizado exitosamente.');
    }

    /**
     * Elimina un usuario de la base de datos.
     */
    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);
        $user->delete();
        return to_route('admin.users.index')->with('success', 'Usuario eliminado exitosamente.');
    }

    /**
     * Activa o desactiva la cuenta de un usuario.
     */
    public function toggleStatus(User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        if ($user->id === auth()->id()) {
            return to_route('admin.users.index')->with('error', 'No puede suspender su propia cuenta.');
        }

        $user->estado_activo = !$user->estado_activo;
        $user->save();

        $message = $user->estado_activo ? 'Usuario reactivado exitosamente.' : 'Usuario suspendido exitosamente.';

        return to_route('admin.users.index')->with('success', $message);
    }
    
    /**
     * Sube y asocia la tarjeta profesional de un usuario.
     */
    public function uploadProfessionalCard(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $request->validate([
            'tarjeta_profesional' => [
                'required',
                File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(2048), // 2MB max
            ],
        ]);

        if ($user->tarjeta_profesional_url) {
            Storage::disk('public')->delete($user->tarjeta_profesional_url);
        }

        $path = $request->file('tarjeta_profesional')->store('professional_cards', 'public');

        $user->tarjeta_profesional_url = $path;
        $user->save();

        return back()->with('success', '¡Tarjeta profesional actualizada exitosamente!');
    }

    /**
     * Almacena un nuevo documento general para un usuario.
     */
    public function storeDocument(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $request->validate([
            'name' => 'required|string|max:255',
            'file' => ['required', File::types(['pdf', 'jpg', 'png', 'doc', 'docx'])->max(5120)], // 5MB max
        ]);

        $path = $request->file('file')->store('user_documents/' . $user->id, 'public');

        $user->documents()->create([
            'name' => $request->name,
            'path' => $path,
        ]);

        return back()->with('success', 'Documento añadido exitosamente.');
    }

    /**
     * Elimina un documento general de un usuario.
     */
    public function destroyDocument(UserDocument $document)
    {
        $this->authorize('update', $document->user); 

        Storage::disk('public')->delete($document->path);
        $document->delete();

        return back()->with('success', 'Documento eliminado exitosamente.');
    }

}