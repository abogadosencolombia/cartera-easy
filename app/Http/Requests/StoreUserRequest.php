<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Solo los administradores pueden crear usuarios.
        // Usamos el 'tipo_usuario' que ya tenemos en el modelo User.
        return $this->user()->tipo_usuario === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Password::defaults()],
            'tipo_usuario' => ['required', Rule::in(['admin', 'gestor', 'abogado', 'cli'])],
            'estado_activo' => 'required|boolean',
            // La cooperativa solo es obligatoria si el tipo de usuario es 'cli'.
            'cooperativa_id' => [
                Rule::requiredIf($this->tipo_usuario === 'cli'),
                'nullable',
                'exists:cooperativas,id' // Asegura que la cooperativa seleccionada exista en la BD.
            ],
        ];
    }
}
