<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePersonaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();
        $persona = $this->route('persona');
        $personaId = $persona instanceof \App\Models\Persona ? $persona->id : $persona;

        return [
            'nombre_completo'    => 'required|string|max:255',
            'tipo_documento'     => 'required|string|max:255',
            
            'numero_documento'   => [
                'required',
                'string',
                'max:255',
                Rule::unique('personas', 'numero_documento')
                    ->whereNull('deleted_at')
                    ->ignore($personaId)
            ],

            // --- FECHAS ---
            'fecha_expedicion'   => 'nullable|date',
            'fecha_nacimiento'   => 'nullable|date|before:today', // <--- NUEVA REGLA

            'telefono_fijo'      => 'nullable|string|max:255',
            'celular_1'          => 'nullable|string|max:255',
            'celular_2'          => 'nullable|string|max:255',
            'correo_1'           => 'nullable|email|max:255',
            'correo_2'           => 'nullable|email|max:255',
            'empresa'            => 'nullable|string|max:255',
            'cargo'              => 'nullable|string|max:255',
            'observaciones'      => 'nullable|string',

            // Legacy
            'direccion'          => 'nullable|string|max:255',
            'ciudad'             => 'nullable|string|max:255',

            // Direcciones Dinámicas
            'addresses'             => ['nullable', 'array', 'max:20'],
            'addresses.*.label'     => ['required_with:addresses.*.address', 'nullable', 'string', 'max:255'],
            'addresses.*.address'   => ['required_with:addresses.*.label', 'nullable', 'string', 'max:1024'],
            'addresses.*.city'      => ['required_with:addresses.*.address', 'nullable', 'string', 'max:255'],

            // Redes Sociales
            'social_links'          => ['nullable','array','max:50'],
            'social_links.*.label'  => ['nullable','string','max:50'],
            'social_links.*.url'    => ['nullable','url','max:2048'],

            // Relaciones
            'cooperativas_ids'      => [
                'nullable', 
                'array',
                function ($attribute, $value, $fail) use ($user) {
                    if ($user->tipo_usuario !== 'admin') {
                        $allowed = $user->cooperativas->pluck('id')->toArray();
                        foreach ($value as $id) {
                            if (!in_array($id, $allowed)) {
                                $fail('No tienes permiso para asignar personas a la cooperativa con ID: ' . $id);
                            }
                        }
                    }
                }
            ],
            'cooperativas_ids.*'    => ['integer', 'exists:cooperativas,id'],
            'abogados_ids'          => ['nullable', 'array'],
            'abogados_ids.*'        => ['integer', 'exists:users,id'],
        ];
    }
}