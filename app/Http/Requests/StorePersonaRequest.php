<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule; // Asegúrate de importar Rule

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
            // --- CAMPOS OBLIGATORIOS ---
            'nombre_completo'    => 'required|string|max:255',
            'tipo_documento'     => 'required|string|max:255',
            'numero_documento'   => [
                'required',
                'string',
                'max:255',
                Rule::unique('personas', 'numero_documento')->whereNull('deleted_at') // Ignora los borrados
            ],

            // --- CAMPOS OPCIONALES ---
            'fecha_expedicion'   => 'nullable|date',
            'telefono_fijo'      => 'nullable|string|max:255',
            'celular_1'          => 'nullable|string|max:255',
            'celular_2'          => 'nullable|string|max:255',
            'correo_1'           => 'nullable|email|max:255',
            'correo_2'           => 'nullable|email|max:255',
            'empresa'            => 'nullable|string|max:255',
            'cargo'              => 'nullable|string|max:255',
            'observaciones'      => 'nullable|string',

            // --- CAMPOS AÑADIDOS QUE FALTABAN ---
            'direccion'          => 'nullable|string|max:255',
            'ciudad'             => 'nullable|string|max:255',
            // ------------------------------------

            // Reglas para Direcciones Dinámicas
            'addresses'            => ['nullable', 'array', 'max:20'],
            'addresses.*.label'    => ['required_with:addresses.*.address', 'nullable', 'string', 'max:255'],
            'addresses.*.address'  => ['required_with:addresses.*.label', 'nullable', 'string', 'max:1024'],
            'addresses.*.city'     => ['required_with:addresses.*.address', 'nullable', 'string', 'max:255'],

            // Reglas para Redes Sociales
            'social_links'         => ['nullable','array','max:50'],
            'social_links.*.label' => ['nullable','string','max:50'],
            'social_links.*.url'   => ['nullable','url','max:2048'],

            // Reglas para las Cooperativas Asignadas
            'cooperativas_ids'         => ['nullable', 'array'],
            'cooperativas_ids.*'       => ['integer', 'exists:cooperativas,id'],
            
            // Reglas para los Abogados Asignados
            'abogados_ids'             => ['nullable', 'array'],
            'abogados_ids.*'           => ['integer', 'exists:users,id'],
        ];
    }
}

