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
        $user = $this->user();
        return [
            'cooperativa_id' => [
                'required', 
                'exists:cooperativas,id',
                function ($attribute, $value, $fail) use ($user) {
                    if ($user->tipo_usuario !== 'admin') {
                        $allowed = $user->cooperativas->pluck('id')->toArray();
                        if (!in_array($value, $allowed)) {
                            $fail('No tienes permiso para asignar casos a esta cooperativa/empresa.');
                        }
                    }
                }
            ],
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
            'link_expediente' => ['nullable', 'url'],
            'monto_total' => ['required', 'numeric'],
            'monto_deuda_actual' => ['nullable', 'numeric'],
            'monto_total_pagado' => ['nullable', 'numeric'],
            'tasa_interes_corriente' => ['required', 'numeric'],
            'fecha_inicio_credito' => ['nullable', 'date'],
            'notas_legales' => ['nullable', 'string'],
            
            // DEUDOR HÍBRIDO EN UPDATE
            'deudor_id' => ['nullable', 'exists:personas,id'],
            'deudor' => ['required', 'array'],
            'deudor.id' => ['required_if:deudor.is_new,false', 'nullable', 'exists:personas,id'],
            'deudor.is_new' => ['required', 'boolean'],
            'deudor.nombre_completo' => ['required_if:deudor.is_new,true', 'nullable', 'string'],
            'deudor.tipo_documento' => ['required_if:deudor.is_new,true', 'nullable', 'string'],
            'deudor.numero_documento' => ['required_if:deudor.is_new,true', 'nullable', 'string'],
            'deudor.dv' => ['nullable', 'string', 'max:1'],
            'deudor.celular_1' => ['nullable', 'string', 'max:20'],
            'deudor.correo_1' => ['nullable', 'email', 'max:255'],
            'deudor.cooperativas_ids' => ['required_if:deudor.is_new,true', 'nullable', 'array', 'min:1'],
            'deudor.abogados_ids' => ['nullable', 'array'],
            
            'codeudores' => ['nullable', 'array'],
            'codeudores.*.id' => ['nullable', 'exists:codeudores,id'],
            'codeudores.*.nombre_completo' => ['required', 'string', 'max:255'],
            'codeudores.*.numero_documento' => ['required', 'string', 'max:50'],
            'codeudores.*.dv' => ['nullable', 'string', 'max:1'],
            'codeudores.*.tipo_documento' => ['nullable', 'string', 'max:10'],
            'codeudores.*.celular' => ['nullable', 'string', 'max:20'],
            'codeudores.*.correo' => ['nullable', 'email', 'max:255'],
            'codeudores.*.addresses' => ['nullable', 'array'],
            'codeudores.*.social_links' => ['nullable', 'array'],
            'juzgado_id' => ['nullable', 'integer', 'exists:juzgados,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'deudor.cooperativas_ids.required_if' => 'Debe asignar al menos una cooperativa o empresa al nuevo deudor.',
            'deudor.cooperativas_ids.min' => 'Debe seleccionar al menos una cooperativa o empresa.',
            'deudor.nombre_completo.required_if' => 'El nombre del deudor es obligatorio.',
            'deudor.numero_documento.required_if' => 'El número de documento es obligatorio.',
            'monto_total.required' => 'El monto del crédito es obligatorio.',
            'tasa_interes_corriente.required' => 'La tasa de interés es obligatoria.',
            'user_id.required' => 'Debe asignar al menos un responsable.',
            'cooperativa_id.required' => 'Debe seleccionar la cooperativa o empresa del caso.',
        ];
    }
}
