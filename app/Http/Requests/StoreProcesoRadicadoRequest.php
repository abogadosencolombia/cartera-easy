<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProcesoRadicadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Define las reglas de validación que se aplican a la petición.
     * Esta es la versión completa y definitiva que incluye TODOS los campos.
     */
    public function rules(): array
    {
        return [
            // --- CAMPOS DE TEXTO Y FECHA (La causa del error anterior) ---
            'radicado'                 => ['required', 'string', 'max:255', 'unique:proceso_radicados,radicado'],
            'fecha_radicado'           => ['nullable', 'date'],
            'naturaleza'               => ['nullable', 'string', 'max:255'],
            'asunto'                   => ['nullable', 'string', 'max:500'],
            'correo_radicacion'        => ['nullable', 'email', 'max:255'],
            'fecha_revision'           => ['nullable', 'date'],
            'fecha_proxima_revision'   => ['nullable', 'date'],
            'ultima_actuacion'         => ['nullable', 'string'],
            'link_expediente'          => ['nullable', 'string', 'max:1024'],
            'ubicacion_drive'          => ['nullable', 'string', 'max:1024'],
            'correos_juzgado'          => ['nullable', 'string'],
            'observaciones'            => ['nullable', 'string'],

            // --- CAMPOS DE RELACIÓN (IDs) ---
            'abogado_id'               => ['required', 'exists:users,id'],
            'responsable_revision_id'  => ['required', 'exists:users,id'],
            'juzgado_id'               => ['required', 'exists:juzgados,id'],
            'tipo_proceso_id'          => ['required', 'exists:tipos_proceso,id'],
            'demandante_id'            => ['required', 'exists:personas,id'],
            'demandado_id'             => ['required', 'exists:personas,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'radicado'                 => 'número de radicado',
            'fecha_radicado'           => 'fecha de radicado',
            'abogado_id'               => 'abogado / gestor',
            'responsable_revision_id'  => 'responsable de revisión',
            'juzgado_id'               => 'juzgado / entidad',
            'tipo_proceso_id'          => 'tipo de proceso',
            'demandante_id'            => 'demandante / denunciante',
            'demandado_id'             => 'demandado / denunciado',
        ];
    }
}

