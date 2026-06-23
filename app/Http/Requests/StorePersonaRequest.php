<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePersonaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'es_demandado' => $this->normalizeBooleanInput($this->input('es_demandado')),
            'estado_cartera' => $this->normalizeEstadoCartera($this->input('estado_cartera')),
            'sin_empresa_o_cooperativa' => $this->normalizeBooleanInput($this->input('sin_empresa_o_cooperativa')),
        ]);
    }

    private function normalizeEstadoCartera($value): string
    {
        $value = strtoupper(trim((string) $value));

        return in_array($value, ['ACTIVO', 'CASTIGADO', 'NO APLICA'], true) ? $value : 'NO APLICA';
    }

    private function normalizeBooleanInput($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $value;
    }

    public function rules(): array
    {
        $user = $this->user();
        return [
            'nombre_completo'    => 'required|string|max:255',
            'tipo_documento'     => 'required|string|max:255',
            
            'numero_documento'   => [
                'required',
                'string',
                'max:255',
                Rule::unique('personas', 'numero_documento')->whereNull('deleted_at'),
            ],
            'dv'                 => 'nullable|string|max:1',

            // --- FECHAS ---
            'fecha_expedicion'   => 'nullable|date',
            'fecha_nacimiento'   => 'nullable|date|before:today', // <--- NUEVA REGLA

            'telefono_fijo'      => 'nullable|string|max:255',
            'celular_1'          => 'nullable|string|max:255',
            'celular_2'          => 'nullable|string|max:255',
            'correo_1'           => 'nullable|email|max:255',
            'correo_2'           => 'nullable|email|max:255',
            'es_demandado'       => 'nullable|boolean',
            'estado_cartera'     => ['nullable', Rule::in(['ACTIVO', 'CASTIGADO', 'NO APLICA'])],
            'sin_empresa_o_cooperativa' => 'nullable|boolean',
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
            'cooperativas_ids'      => ['nullable', 'array'],
            'cooperativas_ids.*'    => ['integer', 'exists:cooperativas,id'],
            'abogados_ids'          => ['nullable', 'array'],
            'abogados_ids.*'        => ['integer', 'exists:users,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'numero_documento' => 'Número de Identificación',
        ];
    }
}
