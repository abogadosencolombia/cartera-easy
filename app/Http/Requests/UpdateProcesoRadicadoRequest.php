<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProcesoRadicadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $procesoId = $this->route('proceso')->id;

        return [
            // --- CAMPOS REQUERIDOS ---
            'tipo_proceso_id'      => ['required', 'exists:tipos_proceso,id'],
            'abogado_id'           => ['required', 'exists:users,id'],
            'fecha_proxima_revision' => ['required', 'date'],
            
            // --- CAMBIO: ARRAYS MULTIPLES ---
            'demandantes'          => ['required', 'array', 'min:1'],
            'demandados'           => ['required', 'array', 'min:1'],

            // --- CAMPOS OPCIONALES ---
            'radicado'             => ['nullable', 'string', 'max:255', Rule::unique('proceso_radicados')->ignore($procesoId)],
            'fecha_radicado'       => ['nullable', 'date'],
            'naturaleza'           => ['nullable', 'string', 'max:255'],
            'asunto'               => ['nullable', 'string', 'max:500'],
            'correo_radicacion'    => ['nullable', 'email', 'max:255'],
            'fecha_revision'       => ['nullable', 'date'],
            'link_expediente'      => ['nullable', 'string', 'max:1024'],
            'ubicacion_drive'      => ['nullable', 'string', 'max:1024'],
            'correos_juzgado'      => ['nullable', 'string'],
            'observaciones'        => ['nullable', 'string'],
            'responsable_revision_id' => ['nullable', 'exists:users,id'],
            'juzgado_id'           => ['nullable', 'exists:juzgados,id'],
        ];
    }
}