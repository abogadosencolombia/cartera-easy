<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->tipo_usuario === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
{
    $userToUpdate = $this->route('user');

    return [
        'name' => 'required|string|max:255',
        'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($userToUpdate->id)],
        'password' => ['nullable', 'confirmed', Password::defaults()],
        
        // --- REGLA CORREGIDA ---
        // Ahora permite 'cliente' y los demás roles que tienes.
        'tipo_usuario' => ['required', Rule::in(['admin', 'abogado', 'cliente', 'gestor'])], 
        
        'estado_activo' => 'required|boolean',
        'persona_id' => [
            'nullable', // Lo hacemos nullable para todos
            Rule::requiredIf($this->input('tipo_usuario') === 'cliente'), // Pero requerido si es cliente
            'exists:personas,id'
        ],
        
        'cooperativas' => 'nullable|array',
        'cooperativas.*' => 'exists:cooperativas,id',
        'preferencias_notificacion' => 'required|array',
        'preferencias_notificacion.email' => 'required|boolean',
        'preferencias_notificacion.in-app' => 'required|boolean',
    ];
}
}