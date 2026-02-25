<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProcesoRadicadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            // --- CAMPOS DE TEXTO Y FECHA ---
            'radicado'             => ['nullable', 'string', 'max:255', 'unique:proceso_radicados,radicado'],
            'fecha_radicado'       => ['nullable', 'date'],
            'naturaleza'           => ['nullable', 'string', 'max:255'],
            'asunto'               => ['nullable', 'string', 'max:500'],
            'correo_radicacion'    => ['nullable', 'email', 'max:255'],
            'fecha_revision'       => ['nullable', 'date'],
            'fecha_proxima_revision' => ['required', 'date'],
            'link_expediente'      => ['nullable', 'string', 'max:1024'],
            'ubicacion_drive'      => ['nullable', 'string', 'max:1024'],
            'correos_juzgado'      => ['nullable', 'string'],
            'observaciones'        => ['nullable', 'string'],

            // --- CAMPOS DE RELACIÓN ---
            'abogado_id'              => ['required', 'exists:users,id'],
            'responsable_revision_id' => ['nullable', 'exists:users,id'],
            'juzgado_id'              => ['nullable', 'exists:juzgados,id'],
            'tipo_proceso_id'         => ['required', 'exists:tipos_proceso,id'],
            
            // --- CAMBIO: AHORA SON ARRAYS ---
            'demandantes'             => ['required', 'array', 'min:1'],
            'demandantes.*'           => ['exists:personas,id'],
            'demandados'              => ['nullable', 'array'],
            'demandados.*'            => ['exists:personas,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'radicado'             => 'número de radicado',
            'abogado_id'           => 'abogado / gestor',
            'demandantes'          => 'demandantes',
            'demandados'           => 'demandados',
            'fecha_proxima_revision' => 'fecha próxima revisión',
        ];
    }
}