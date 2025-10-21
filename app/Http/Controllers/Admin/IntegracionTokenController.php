<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IntegracionToken;
use App\Http\Requests\StoreIntegracionTokenRequest;
use App\Http\Requests\UpdateIntegracionTokenRequest;
use Inertia\Inertia;

class IntegracionTokenController extends Controller
{
    /**
     * Muestra una lista de todas las credenciales de integración.
     */
    public function index()
    {
        $tokens = IntegracionToken::latest()->paginate(10);
        
        return Inertia::render('Admin/Tokens/Index', [
            'tokens' => $tokens
        ]);
    }

    /**
     * Muestra el formulario para crear una nueva credencial.
     */
    public function create()
    {
        return Inertia::render('Admin/Tokens/Create');
    }

    /**
     * Almacena una nueva credencial en la base de datos.
     */
    public function store(StoreIntegracionTokenRequest $request)
    {
        IntegracionToken::create($request->validated());

        return redirect()->route('admin.tokens.index')
            ->with('success', 'Credencial creada exitosamente.');
    }

    /**
     * Muestra el formulario para editar una credencial existente.
     */
    public function edit(IntegracionToken $token)
    {
        return Inertia::render('Admin/Tokens/Edit', [
            'token' => $token
        ]);
    }

    /**
     * Actualiza una credencial existente en la base de datos.
     */
    public function update(UpdateIntegracionTokenRequest $request, IntegracionToken $token)
    {
        $token->update($request->validated());

        return redirect()->route('admin.tokens.index')
            ->with('success', 'Credencial actualizada exitosamente.');
    }

    /**
     * Elimina una credencial de la base de datos.
     */
    public function destroy(IntegracionToken $token)
    {
        $token->delete();

        return redirect()->route('admin.tokens.index')
            ->with('success', 'Credencial eliminada exitosamente.');
    }
}
