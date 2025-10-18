<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePersonaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre_completo'    => 'required|string|max:255',
            'tipo_documento'     => 'required|string|max:255',
            'numero_documento'   => 'required|string|max:255|unique:personas,numero_documento',
            'fecha_expedicion'   => 'nullable|date',
            'telefono_fijo'      => 'nullable|string|max:255',
            'celular_1'          => 'nullable|string|max:255',
            'celular_2'          => 'nullable|string|max:255',
            'correo_1'           => 'nullable|email|max:255',
            'correo_2'           => 'nullable|email|max:255',
            'empresa'            => 'nullable|string|max:255',
            'cargo'              => 'nullable|string|max:255',
            'observaciones'      => 'nullable|string',

            // Reglas para Direcciones Dinámicas
            'addresses'             => ['nullable', 'array', 'max:20'], // Máximo 20 direcciones por persona
            'addresses.*.label'     => ['required_with:addresses.*.address', 'nullable', 'string', 'max:255'],
            'addresses.*.address'   => ['required_with:addresses.*.label', 'nullable', 'string', 'max:1024'],
            'addresses.*.city'      => ['required_with:addresses.*.address', 'nullable', 'string', 'max:255'],

            // Reglas para Redes Sociales
            'social_links'         => ['nullable','array','max:50'],
            'social_links.*.label' => ['nullable','string','max:50'],
            'social_links.*.url'   => ['nullable','url','max:2048'],
        ];
    }
}

