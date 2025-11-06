<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCasoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('caso'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // --- DATOS BÁSICOS DEL CASO ---
            'cooperativa_id' => ['required', 'exists:cooperativas,id'],
            'user_id' => ['required', 'exists:users,id'],
            'referencia_credito' => ['nullable', 'string', 'max:255'],
            'radicado' => ['nullable', 'string', 'max:255'],
            
            // ===== INICIO: CORRECCIÓN (OPCIONAL) =====
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
            // ===== FIN: CORRECCIÓN =====
            
            'estado_proceso' => ['required', Rule::in(['prejurídico', 'demandado', 'en ejecución', 'sentencia', 'cerrado'])],
            'tipo_garantia_asociada' => ['required', Rule::in(['codeudor', 'hipotecaria', 'prendaria', 'sin garantía'])],
            'fecha_apertura' => ['required', 'date', 'before_or_equal:today'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha_apertura'],
            'origen_documental' => ['required', Rule::in(['pagaré', 'libranza', 'contrato', 'otro'])],

            // --- DATOS FINANCIEROS (CORREGIDOS) ---
            'monto_total' => ['required', 'numeric', 'min:0'],
            'tasa_interes_corriente' => ['required', 'numeric', 'min:0', 'max:100'],
            
            // ===== INICIO: CORRECCIÓN =====
            'tasa_moratoria' => ['nullable', 'numeric', 'min:0'],
            'fecha_tasa_interes' => ['nullable', 'date'],
            // ===== FIN: CORRECCIÓN =====

            // --- PERSONAS INVOLUCRADAS ---
            'deudor_id' => ['required', 'exists:personas,id'],
            
            'codeudores' => ['nullable', 'array'],
            'codeudores.*' => ['required', 'array'],
            'codeudores.*.nombre_completo' => ['required', 'string', 'max:255'],
            'codeudores.*.numero_documento' => ['required', 'string', 'max:50'],
            'codeudores.*.tipo_documento' => ['nullable', 'string', 'max:10'],
            'codeudores.*.celular' => ['nullable', 'string', 'max:20'],
            'codeudores.*.correo' => ['nullable', 'email', 'max:255'], 
            'codeudores.*.addresses' => ['nullable', 'json'],
            'codeudores.*.social_links' => ['nullable', 'json'],

            // --- LÓGICA DE CONTROL Y BLOQUEO ---
            'etapa_actual' => ['nullable', 'string', 'max:255'],
            'notas_legales' => ['nullable', 'string'],
            'medio_contacto' => ['nullable', 'string', 'max:100'],
            'bloqueado' => ['required', 'boolean'],
            'motivo_bloqueo' => ['nullable', 'string', 'max:1000', 'required_if:bloqueado,true'],

            // ===== INICIO: CORRECCIÓN (OPCIONAL Y SEGURA) =====
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
            // ===== FIN: CORRECCIÓN =====

            // --- INICIO: CORRECCIÓN L4 (OPCIONAL Y SEGURA) ---
            'subproceso' => [
                'nullable', // Permitir nulo si no aplica
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
            // --- FIN: CORRECCIÓN L4 ---

            'etapa_procesal' => ['nullable', 'string', Rule::exists('etapas_procesales', 'nombre')],
            'juzgado_id' => ['nullable', 'integer', 'exists:juzgados,id'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $caso = $this->route('caso');

        // Normaliza boolean; si no viene, usa el valor actual
        $bloqueado = $this->boolean('bloqueado');
        if (is_null($this->input('bloqueado'))) {
            $bloqueado = (bool) ($caso->bloqueado ?? false);
        }

        if (($this->user()->tipo_usuario ?? null) !== 'admin') {
            // No-admin: no pueden cambiar bloqueo ni motivo
            // --- INICIO: LÍNEA CORREGIDA ---
            $this->replace(array_merge($this->all(), [
            // --- FIN: LÍNEA CORREGIDA ---
                'bloqueado' => (bool) $caso->bloqueado,
                'motivo_bloqueo' => $caso->motivo_bloqueo,
            ]));
        } else {
            // Admin: limpia motivo si desbloquea
            $this->merge([
                'bloqueado' => $bloqueado,
                'motivo_bloqueo' => $bloqueado ? $this->input('motivo_bloqueo') : null,
            ]);
        }
    }
}

