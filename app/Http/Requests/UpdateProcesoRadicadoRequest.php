<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProcesoRadicadoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        // Obtenemos el ID del proceso que se está actualizando desde la ruta.
        $procesoId = $this->route('proceso')->id;

        return [
            // --- Campos de Relación (Obligatorios) ---
            'abogado_id'               => ['required', 'exists:users,id'],
            'responsable_revision_id'  => ['required', 'exists:users,id'],
            'juzgado_id'               => ['required', 'exists:juzgados,id'],
            'tipo_proceso_id'          => ['required', 'exists:tipos_proceso,id'],
            'demandante_id'            => ['required', 'exists:personas,id'],
            'demandado_id'             => ['required', 'exists:personas,id'],
            
            // --- Campos Principales de Texto y Fecha ---
            // La regla 'unique' ignora el registro actual para permitir la actualización.
            'radicado'                 => ['required', 'string', 'max:255', Rule::unique('proceso_radicados')->ignore($procesoId)],
            'fecha_radicado'           => ['nullable', 'date'],
            'naturaleza'               => ['nullable', 'string', 'max:255'],
            'asunto'                   => ['nullable', 'string'],
            'correo_radicacion'        => ['nullable', 'email', 'max:255'],
            'fecha_revision'           => ['nullable', 'date'],
            'fecha_proxima_revision'   => ['nullable', 'date'],
            'ultima_actuacion'         => ['nullable', 'string'],
            'link_expediente'          => ['nullable', 'url', 'max:1024'],
            'ubicacion_drive'          => ['nullable', 'url', 'max:1024'],
            'correos_juzgado'          => ['nullable', 'string'],
            'observaciones'            => ['nullable', 'string'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'abogado_id'               => 'abogado / gestor',
            'responsable_revision_id'  => 'responsable de revisión',
            'juzgado_id'               => 'juzgado / entidad',
            'tipo_proceso_id'          => 'tipo de proceso',
            'demandante_id'            => 'demandante / denunciante',
            'demandado_id'             => 'demandado / denunciado',
            'fecha_radicado'           => 'fecha de radicado',
            'correo_radicacion'        => 'correo de radicación',
            'fecha_proxima_revision'   => 'fecha de próxima revisión',
            'ultima_actuacion'         => 'última actuación',
            'link_expediente'          => 'link de expediente digital',
            'ubicacion_drive'          => 'ubicación en Drive',
        ];
    }
}
