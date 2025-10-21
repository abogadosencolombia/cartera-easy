<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCooperativaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // El controller ya está usando $this->authorize(), así que aquí
        // podemos simplemente retornar true.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        // Obtenemos el ID de la cooperativa que estamos editando desde la ruta.
        $cooperativaId = $this->route('cooperativa')->id;

        return [
            // Para los campos 'nombre' y 'NIT', la regla 'unique' debe ignorar a la
            // cooperativa que estamos editando. Esto es CRÍTICO.
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('cooperativas', 'nombre')->ignore($cooperativaId)
            ],
            'NIT' => [
                'required',
                'string',
                'max:255',
                Rule::unique('cooperativas', 'NIT')->ignore($cooperativaId)
            ],
            
            // Las demás reglas pueden ser iguales a las de creación.
            'tipo_vigilancia' => ['required', Rule::in(['Supersolidaria', 'SFC', 'Otro'])],
            'fecha_constitucion' => ['required', 'date'],
            'numero_matricula_mercantil' => ['nullable', 'string', 'max:255'],
            'tipo_persona' => ['required', Rule::in(['Natural', 'Jurídica'])],
            'representante_legal_nombre' => ['required', 'string', 'max:255'],
            'representante_legal_cedula' => ['required', 'string', 'max:255'],
            'contacto_nombre' => ['required', 'string', 'max:255'],
            'contacto_telefono' => ['required', 'string', 'max:255'],
            'contacto_correo' => ['required', 'email', 'max:255'],
            'correo_notificaciones_judiciales' => ['required', 'email', 'max:255'],
            'usa_libranza' => ['required', 'boolean'],
            'requiere_carta_instrucciones' => ['required', 'boolean'],
            'tipo_garantia_frecuente' => ['required', Rule::in(['codeudor', 'hipotecaria', 'prendaria', 'sin garantía'])],
            'tasa_maxima_moratoria' => ['required', 'string', 'max:255'],
            'ciudad_principal_operacion' => ['nullable', 'string', 'max:255'],
            'estado_activo' => ['required', 'boolean'],
        ];
    }
}