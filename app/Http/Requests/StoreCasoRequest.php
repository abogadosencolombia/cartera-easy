<?php

namespace App\Http\Requests; // <-- ¡AQUÍ ESTABA EL ERROR! Corregido.

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCasoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Asumo que tienes un Policy para Caso, si no, ajusta esto
        return $this->user()->can('create', \App\Models\Caso::class);
    }

    public function rules(): array
    {
        return [
            // --- DATOS BÁSICOS DEL CASO ---
            'cooperativa_id' => ['required', 'exists:cooperativas,id'],
            'user_id' => ['required', 'exists:users,id'],
            'referencia_credito' => ['nullable', 'string', 'max:255'],

            // --- INICIO: CAMBIO ---
            'radicado' => ['nullable', 'string', 'max:255'], // <-- AÑADIDO (Ya estaba)
            // --- FIN: CAMBIO ---

            // ===== INICIO: CORRECCIÓN (OPCIONAL) =====
            'especialidad_id' => ['nullable', 'integer', 'exists:especialidades_juridicas,id'],
            // ===== FIN: CORRECCIÓN =====

            // Verifica que el tipo_proceso exista en la tabla tipos_proceso
            // ===== INICIO: CORRECCIÓN (OPCIONAL Y SEGURA) =====
            'tipo_proceso' => [
                'nullable', 
                'string',
                Rule::exists('tipos_proceso', 'nombre')->where(function ($query) {
                    if ($this->input('especialidad_id')) {
                        // Si el padre (L1) existe, el hijo (L2) DEBE pertenecer a él.
                        $query->where('especialidad_juridica_id', $this->input('especialidad_id'));
                    } else {
                        // Si el padre (L1) es nulo, esta regla falla si tipo_proceso no es nulo.
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

            // --- LÍNEA AÑADIDA ---
            'medio_contacto' => ['nullable', 'string', 'max:100'],
            // --- FIN LÍNEA AÑADIDA ---

            // --- DATOS FINANCIEROS ---
            'monto_total' => ['required', 'numeric', 'min:0'],
            'tasa_interes_corriente' => ['required', 'numeric', 'min:0', 'max:100'],
            // 'tasa_moratoria' => ['required', 'numeric', 'min:0'], // <-- ELIMINADO
            'fecha_tasa_interes' => ['nullable', 'date', 'after_or_equal:fecha_apertura'], // <-- AÑADIDO

            // --- PERSONAS INVOLUCRADAS ---
            'deudor_id' => ['required', 'exists:personas,id'],
            
            // --- INICIO: CAMBIO (Validación de Codeudores) ---
            // 'codeudor1_id' => ['nullable', 'exists:personas,id', 'different:deudor_id'], // <-- ELIMINADO
            // 'codeudor2_id' => ['nullable', 'exists:personas,id', 'different:deudor_id', 'different:codeudor1_id'], // <-- ELIMINADO

            'codeudores' => ['nullable', 'array'],
            'codeudores.*' => ['required', 'array'],
            'codeudores.*.nombre_completo' => ['required', 'string', 'max:255'],
            'codeudores.*.numero_documento' => ['required', 'string', 'max:50'],
            'codeudores.*.tipo_documento' => ['nullable', 'string', 'max:10'],
            'codeudores.*.celular' => ['nullable', 'string', 'max:20'],
            // Usamos 'correo' como en el controlador
            'codeudores.*.correo' => ['nullable', 'email', 'max:255'], 
            'codeudores.*.addresses' => ['nullable', 'json'],
            'codeudores.*.social_links' => ['nullable', 'json'],
            // --- FIN: CAMBIO ---

            'clonado_de_id' => ['nullable', 'exists:casos,id'],

            // Verifica que el subtipo_proceso exista y pertenezca al tipo_proceso seleccionado
            // ===== INICIO: CORRECCIÓN (OPCIONAL Y SEGURA) =====
            'subtipo_proceso' => [
                'nullable', // Permitir nulo si no aplica
                'string',
                Rule::exists('subtipos_proceso', 'nombre')->where(function ($query) {
                    $tipoProcesoId = \App\Models\TipoProceso::where('nombre', $this->input('tipo_proceso'))->value('id');
                    if ($tipoProcesoId) {
                        // Si el padre (L2) existe, el hijo (L3) DEBE pertenecer a él.
                        $query->where('tipo_proceso_id', $tipoProcesoId);
                    } else {
                        // Si el padre (L2) es nulo, esta regla falla si subtipo_proceso no es nulo.
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
                    
                    if ($tipoProcesoId) { // Solo buscar el L3 si L2 existe
                        $subtipoProcesoId = \App\Models\SubtipoProceso::where('nombre', $this->input('subtipo_proceso'))
                                                                     ->where('tipo_proceso_id', $tipoProcesoId)
                                                                     ->value('id');
                    }
                    
                    if ($subtipoProcesoId) {
                        // Si el padre (L3) existe, el hijo (L4) DEBE pertenecer a él.
                        $query->where('subtipo_proceso_id', $subtipoProcesoId);
                    } else {
                        // Si el padre (L3) es nulo, esta regla falla si subproceso no es nulo.
                        $query->where('subtipo_proceso_id', null);
                    }
                }),
            ],
            // --- FIN: CORRECCIÓN L4 ---

            'etapa_procesal' => ['nullable', 'string', Rule::exists('etapas_procesales', 'nombre')],
            'juzgado_id' => ['nullable', 'integer', 'exists:juzgados,id'],
        ];
    }
}

