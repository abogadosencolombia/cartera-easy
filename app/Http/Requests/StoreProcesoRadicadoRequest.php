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
            'etapa_procesal_id'       => ['nullable', 'exists:etapas_procesales,id'],
            
            // --- CAMBIO: AHORA SON ARRAYS DE OBJETOS O IDS ---
            'demandantes'             => ['required', 'array', 'min:1'],
            'demandantes.*.id'        => ['nullable', 'integer', 'exists:personas,id'],
            'demandantes.*.is_new'    => ['nullable', 'boolean'],
            'demandantes.*.nombre_completo' => ['required_if:demandantes.*.is_new,true', 'nullable', 'string', 'max:255'],
            'demandantes.*.numero_documento' => ['required_if:demandantes.*.is_new,true', 'nullable', 'string', 'max:255'],
            'demandantes.*.tipo_documento'   => ['required_if:demandantes.*.is_new,true', 'nullable', 'string', 'max:255'],

            'demandados'              => ['nullable', 'array'],
            'demandados.*.id'         => ['nullable', 'integer', 'exists:personas,id'],
            'demandados.*.is_new'     => ['nullable', 'boolean'],
            'demandados.*.nombre_completo' => ['required_if:demandados.*.is_new,true', 'nullable', 'string', 'max:255'],
            'demandados.*.numero_documento' => ['required_if:demandados.*.sin_info,false', 'required_if:demandados.*.is_new,true', 'nullable', 'string', 'max:255'],
            'demandados.*.tipo_documento'   => ['required_if:demandados.*.is_new,true', 'nullable', 'string', 'max:255'],
            'demandados.*.sin_info'         => ['nullable', 'boolean'],
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