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

            // ===== REGLA CORREGIDA Y DINÁMICA =====
            'tipo_proceso' => ['required', 'string', Rule::exists('tipos_proceso', 'nombre')],
            
            'estado_proceso' => ['required', Rule::in(['prejurídico', 'demandado', 'en ejecución', 'sentencia', 'cerrado'])],
            'tipo_garantia_asociada' => ['required', Rule::in(['codeudor', 'hipotecaria', 'prendaria', 'sin garantía'])],
            'fecha_apertura' => ['required', 'date', 'before_or_equal:today'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha_apertura'],
            'origen_documental' => ['required', Rule::in(['pagaré', 'libranza', 'contrato', 'otro'])],
            
            // --- DATOS FINANCIEROS ---
            'monto_total' => ['required', 'numeric', 'min:0'],
            'tasa_interes_corriente' => ['required', 'numeric', 'min:0', 'max:100'],
            'tasa_moratoria' => ['required', 'numeric', 'min:0'],

            // --- PERSONAS INVOLUCRADAS ---
            'deudor_id' => ['required', 'exists:personas,id'],
            'codeudor1_id' => ['nullable', 'exists:personas,id', 'different:deudor_id'],
            'codeudor2_id' => ['nullable', 'exists:personas,id', 'different:deudor_id', 'different:codeudor1_id'],
            
            // --- LÓGICA DE CONTROL Y BLOQUEO ---
            'etapa_actual' => ['nullable', 'string', 'max:255'],
            'notas_legales' => ['nullable', 'string'],
            'bloqueado' => ['required', 'boolean'],
            'motivo_bloqueo' => ['nullable', 'string', 'max:1000', 'required_if:bloqueado,true'],

            // ===== REGLAS CORREGIDAS Y DINÁMICAS =====
            'subtipo_proceso' => ['nullable', 'string', Rule::exists('subtipos_proceso', 'nombre')],
            'etapa_procesal' => ['nullable', 'string', Rule::exists('etapas_procesales', 'nombre')],
            'juzgado_id' => ['nullable', 'integer', 'exists:juzgados,id'],
        ];
    }

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
            $this->replace(array_merge($this->all(), [
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
