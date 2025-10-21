<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreIntegracionTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Solo los administradores pueden crear tokens.
        return $this->user()->tipo_usuario === 'admin';
    }

    public function rules(): array
    {
        return [
            // El proveedor debe ser único en la tabla y es obligatorio.
            'proveedor' => 'required|string|max:255|unique:integracion_tokens,proveedor',
            'api_key' => 'nullable|string',
            'client_id' => 'nullable|string',
            'client_secret' => 'nullable|string',
            'activo' => 'required|boolean',
        ];
    }
}