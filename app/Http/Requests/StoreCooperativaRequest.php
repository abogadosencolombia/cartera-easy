<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCooperativaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Por ahora, cualquier usuario autenticado puede crear una cooperativa.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Aquí definimos las reglas para cada campo del formulario.
        // Si alguna de estas reglas falla, Laravel automáticamente
        // devolverá al usuario al formulario y mostrará los errores.
        return [
            'nombre' => 'required|string|max:255|unique:cooperativas,nombre',
            'NIT' => 'required|string|max:255|unique:cooperativas,NIT',
            'tipo_vigilancia' => ['required', Rule::in(['Supersolidaria', 'SFC', 'Otro'])],
            'fecha_constitucion' => 'required|date',
            'numero_matricula_mercantil' => 'nullable|string|max:255',
            'tipo_persona' => ['required', Rule::in(['Natural', 'Jurídica'])],
            'representante_legal_nombre' => 'required|string|max:255',
            'representante_legal_cedula' => 'required|string|max:255',
            'contacto_nombre' => 'required|string|max:255',
            'contacto_telefono' => 'required|string|max:255',
            'contacto_correo' => 'required|email|max:255',
            'correo_notificaciones_judiciales' => 'required|email|max:255',
            'usa_libranza' => 'required|boolean',
            'requiere_carta_instrucciones' => 'required|boolean',
            'tipo_garantia_frecuente' => ['required', Rule::in(['codeudor', 'hipotecaria', 'prendaria', 'sin garantía'])],
            'tasa_maxima_moratoria' => 'required|string|max:255',
            'ciudad_principal_operacion' => 'nullable|string|max:255',
        ];
    }
}
