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
        $tokens = IntegracionToken::latest()
            ->paginate(10)
            ->through(fn (IntegracionToken $token) => $this->safeTokenPayload($token));

        return Inertia::render('Admin/Tokens/Index', [
            'tokens' => $tokens,
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
            'token' => $this->safeTokenPayload($token),
        ]);
    }

    /**
     * Actualiza una credencial existente en la base de datos.
     */
    public function update(UpdateIntegracionTokenRequest $request, IntegracionToken $token)
    {
        $token->update($this->withoutBlankSecrets($request->validated()));

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

    private function safeTokenPayload(IntegracionToken $token): array
    {
        return [
            'id' => $token->id,
            'proveedor' => $token->proveedor,
            'client_id' => $token->client_id,
            'activo' => (bool) $token->activo,
            'expira_en' => $token->expira_en,
            'created_at' => $token->created_at?->toJSON(),
            'updated_at' => $token->updated_at?->toJSON(),
            'has_api_key' => filled($token->api_key),
            'has_client_secret' => filled($token->client_secret),
        ];
    }

    private function withoutBlankSecrets(array $data): array
    {
        foreach (['api_key', 'client_secret'] as $field) {
            if (array_key_exists($field, $data) && blank($data[$field])) {
                unset($data[$field]);
            }
        }

        return $data;
    }
}
