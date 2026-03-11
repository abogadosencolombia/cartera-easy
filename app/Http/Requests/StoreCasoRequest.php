<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCasoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Caso::class);
    }

    public function rules(): array
    {
        $user = $this->user();
        return [
            // --- DATOS BÁSICOS DEL CASO ---
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
            'tipo_proceso' => [
                'nullable', 
                'string',
                Rule::exists('tipos_proceso', 'nombre')->where(function ($query) {
                    if ($this->input('especialidad_id')) {
                        $query->where('especialidad_juridica_id', $this->input('especialidad_id'));
                    } else {
                        $query->where('especialidad_juridica_id', null);
                    }
                }),
            ],
            'tipo_garantia_asociada' => ['required', Rule::in(['codeudor', 'hipotecaria', 'prendaria', 'sin garantía'])],
            'fecha_apertura' => ['required', 'date', 'before_or_equal:today'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'origen_documental' => ['required', Rule::in(['pagaré', 'libranza', 'contrato', 'otro'])],
            'medio_contacto' => ['nullable', 'string', 'max:100'],
            'link_drive' => ['nullable', 'url', 'max:2048'],
            'link_expediente' => ['nullable', 'url', 'max:2048'],

            // --- DATOS FINANCIEROS ---
            'monto_total' => ['required', 'numeric', 'min:0'],
            'monto_deuda_actual' => ['nullable', 'numeric', 'min:0'],
            'monto_total_pagado' => ['nullable', 'numeric', 'min:0'],
            'tasa_interes_corriente' => ['required', 'numeric', 'min:0', 'max:100'],
            'fecha_inicio_credito' => ['nullable', 'date', 'before_or_equal:today'],

            // --- DEUDOR HÍBRIDO ---
            'deudor_id' => ['nullable', 'exists:personas,id'],
            'deudor' => ['required', 'array'],
            'deudor.is_new' => ['required', 'boolean'],
            'deudor.nombre_completo' => ['required_if:deudor.is_new,true', 'nullable', 'string', 'max:255'],
            'deudor.tipo_documento' => ['required_if:deudor.is_new,true', 'nullable', 'string', 'max:10'],
            'deudor.numero_documento' => ['required_if:deudor.is_new,true', 'nullable', 'string', 'max:50'],
            'deudor.celular_1' => ['nullable', 'string', 'max:20'],
            'deudor.correo_1' => ['nullable', 'email', 'max:255'],
            'deudor.cooperativas_ids' => ['nullable', 'array'],
            'deudor.abogados_ids' => ['nullable', 'array'],
            
            // --- CODEUDORES ---
            'codeudores' => ['nullable', 'array'],
            'codeudores.*.nombre_completo' => ['required', 'string', 'max:255'],
            'codeudores.*.numero_documento' => ['required', 'string', 'max:50'],
            'codeudores.*.tipo_documento' => ['nullable', 'string', 'max:10'],
            'codeudores.*.celular' => ['nullable', 'string', 'max:20'],
            'codeudores.*.correo' => ['nullable', 'email', 'max:255'],
            'codeudores.*.addresses' => ['nullable', 'array'],
            'codeudores.*.social_links' => ['nullable', 'array'],

            'clonado_de_id' => ['nullable', 'exists:casos,id'],
            'subtipo_proceso' => ['nullable', 'string'],
            'subproceso' => ['nullable', 'string', 'max:255'],
            'etapa_procesal' => ['nullable', 'string'],
            'juzgado_id' => ['nullable', 'integer', 'exists:juzgados,id'],
        ];
    }
}
