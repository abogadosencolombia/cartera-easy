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
            // --- DATOS BÁSICOS DEL CASO ---
            'cooperativa_id' => ['required', 'exists:cooperativas,id'],
            'user_id' => ['required', 'exists:users,id'],
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
            
            // ELIMINADO: estado_proceso
            'tipo_garantia_asociada' => ['required', Rule::in(['codeudor', 'hipotecaria', 'prendaria', 'sin garantía'])],
            'fecha_apertura' => ['required', 'date', 'before_or_equal:today'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha_apertura'],
            'origen_documental' => ['required', Rule::in(['pagaré', 'libranza', 'contrato', 'otro'])],

            // --- LINK DRIVE ---
            'link_drive' => ['nullable', 'url', 'max:2048'],

            // --- DATOS FINANCIEROS ---
            'monto_total' => ['required', 'numeric', 'min:0'],
            'monto_deuda_actual' => ['nullable', 'numeric', 'min:0'],
            'monto_total_pagado' => ['nullable', 'numeric', 'min:0'],

            'tasa_interes_corriente' => ['required', 'numeric', 'min:0', 'max:100'],
            'fecha_inicio_credito' => ['nullable', 'date', 'before_or_equal:today'],

            // --- PERSONAS INVOLUCRADAS ---
            'deudor_id' => ['required', 'exists:personas,id'],
            
            'codeudores' => ['nullable', 'array'],
            'codeudores.*' => ['required', 'array'],
            'codeudores.*.nombre_completo' => ['required', 'string', 'max:255'],
            'codeudores.*.numero_documento' => ['required', 'string', 'max:50'],
            'codeudores.*.tipo_documento' => ['nullable', 'string', 'max:10'],
            'codeudores.*.celular' => ['nullable', 'string', 'max:20'],
            'codeudores.*.correo' => ['nullable', 'email', 'max:255'], 
            
            // --- ARRAYS ---
            'codeudores.*.addresses' => ['nullable', 'array'],
            'codeudores.*.social_links' => ['nullable', 'array'],

            // --- LÓGICA DE CONTROL Y BLOQUEO ---
            'etapa_actual' => ['nullable', 'string', 'max:255'],
            'notas_legales' => ['nullable', 'string'],
            'medio_contacto' => ['nullable', 'string', 'max:100'],
            'bloqueado' => ['required', 'boolean'],
            'motivo_bloqueo' => ['nullable', 'string', 'max:1000', 'required_if:bloqueado,true'],

            'subtipo_proceso' => [
                'nullable',
                'string',
                Rule::exists('subtipos_proceso', 'nombre')->where(function ($query) {
                    $tipoProcesoId = \App\Models\TipoProceso::where('nombre', $this->input('tipo_proceso'))->value('id');
                    if ($tipoProcesoId) {
                        $query->where('tipo_proceso_id', $tipoProcesoId);
                    } else {
                        $query->where('tipo_proceso_id', null);
                    }
                }),
            ],

            'subproceso' => [
                'nullable',
                'string',
                'max:255',
                Rule::exists('subprocesos', 'nombre')->where(function ($query) {
                    $subtipoProcesoId = null;
                    $tipoProcesoId = \App\Models\TipoProceso::where('nombre', $this->input('tipo_proceso'))->value('id');
                    
                    if ($tipoProcesoId) {
                        $subtipoProcesoId = \App\Models\SubtipoProceso::where('nombre', $this->input('subtipo_proceso'))
                                                                        ->where('tipo_proceso_id', $tipoProcesoId)
                                                                        ->value('id');
                    }
                    
                    if ($subtipoProcesoId) {
                        $query->where('subtipo_proceso_id', $subtipoProcesoId);
                    } else {
                        $query->where('subtipo_proceso_id', null);
                    }
                }),
            ],

            'etapa_procesal' => ['nullable', 'string', Rule::exists('etapas_procesales', 'nombre')],
            'juzgado_id' => ['nullable', 'integer', 'exists:juzgados,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $caso = $this->route('caso');

        $bloqueado = $this->boolean('bloqueado');
        if (is_null($this->input('bloqueado'))) {
            $bloqueado = (bool) ($caso->bloqueado ?? false);
        }

        if (($this->user()->tipo_usuario ?? null) !== 'admin') {
            $this->replace(array_merge($this->all(), [
                'bloqueado' => (bool) $caso->bloqueado,
                'motivo_bloqueo' => $caso->motivo_bloqueo,
            ]));
        } else {
            $this->merge([
                'bloqueado' => $bloqueado,
                'motivo_bloqueo' => $bloqueado ? $this->input('motivo_bloqueo') : null,
            ]);
        }
    }
}