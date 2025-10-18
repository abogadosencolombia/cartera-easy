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
        return [
            // --- DATOS BÁSICOS DEL CASO ---
            'cooperativa_id' => ['required', 'exists:cooperativas,id'],
            'user_id' => ['required', 'exists:users,id'],
            'referencia_credito' => ['nullable', 'string', 'max:255'],
            
            // ===== REGLA CORREGIDA Y DINÁMICA =====
            // Ahora comprueba que el valor exista en la tabla 'tipos_proceso'
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
            
            'clonado_de_id' => ['nullable', 'exists:casos,id'],

            // ===== REGLAS CORREGIDAS Y DINÁMICAS =====
            'subtipo_proceso' => ['nullable', 'string', Rule::exists('subtipos_proceso', 'nombre')],
            'etapa_procesal' => ['nullable', 'string', Rule::exists('etapas_procesales', 'nombre')],
            'juzgado_id' => ['nullable', 'integer', 'exists:juzgados,id'],
        ];
    }
}
