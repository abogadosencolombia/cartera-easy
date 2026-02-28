<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCasoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('caso'));
    }

    public function rules(): array
    {
        return [
            'cooperativa_id' => ['required', 'exists:cooperativas,id'],
            'user_id' => ['required', 'array', 'min:1'],
            'user_id.*' => ['exists:users,id'],
            'referencia_credito' => ['nullable', 'string', 'max:255'],
            'radicado' => ['nullable', 'string', 'max:255'],
            'especialidad_id' => ['nullable', 'integer', 'exists:especialidades_juridicas,id'],
            'tipo_proceso' => ['nullable', 'string'],
            'subtipo_proceso' => ['nullable', 'string'],
            'subproceso' => ['nullable', 'string'],
            'etapa_procesal' => ['nullable', 'string'],
            'tipo_garantia_asociada' => ['required'],
            'fecha_apertura' => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'origen_documental' => ['required'],
            'medio_contacto' => ['nullable', 'string'],
            'link_drive' => ['nullable', 'url'],
            'monto_total' => ['required', 'numeric'],
            'monto_deuda_actual' => ['nullable', 'numeric'],
            'monto_total_pagado' => ['nullable', 'numeric'],
            'tasa_interes_corriente' => ['required', 'numeric'],
            'fecha_inicio_credito' => ['nullable', 'date'],
            'notas_legales' => ['nullable', 'string'],
            
            // DEUDOR HÍBRIDO EN UPDATE
            'deudor_id' => ['nullable', 'exists:personas,id'],
            'deudor' => ['required', 'array'],
            'deudor.is_new' => ['required', 'boolean'],
            'deudor.nombre_completo' => ['required_if:deudor.is_new,true', 'nullable', 'string'],
            
            'codeudores' => ['nullable', 'array'],
            'juzgado_id' => ['nullable', 'integer', 'exists:juzgados,id'],
        ];
    }
}
