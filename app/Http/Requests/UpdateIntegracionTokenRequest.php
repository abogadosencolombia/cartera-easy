<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule; // Importante para la regla 'unique'

class UpdateIntegracionTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->tipo_usuario === 'admin';
    }

    public function rules(): array
    {
        // this->route('token') obtiene el modelo del token que se está editando.
        $tokenId = $this->route('token')->id;

        return [
            // El proveedor debe ser único, ignorando su propio registro actual.
            'proveedor' => ['required', 'string', 'max:255', Rule::unique('integracion_tokens')->ignore($tokenId)],
            'api_key' => 'nullable|string',
            'client_id' => 'nullable|string',
            'client_secret' => 'nullable|string',
            'activo' => 'required|boolean',
        ];
    }
}