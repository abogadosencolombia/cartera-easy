<?php

namespace App\Policies;

use App\Models\Persona;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PersonaPolicy
{
    /**
     * Un administrador puede hacer cualquier cosa.
     */
    public function before(User $user, string $ability): bool|null
    {
        if ($user->tipo_usuario === 'admin') {
            return true;
        }
        return null;
    }

    /**
     * Determina si el usuario puede ver el listado de personas.
     */
    public function viewAny(User $user): bool
    {
        // Gestores y abogados necesitan ver el directorio para crear casos.
        return in_array($user->tipo_usuario, ['admin', 'gestor', 'abogado']);
    }

    /**
     * Determina si el usuario puede ver una persona específica.
     */
    public function view(User $user, Persona $persona): bool
    {
        return in_array($user->tipo_usuario, ['admin', 'gestor', 'abogado']);
    }

    /**
     * Determina si el usuario puede crear personas.
     */
    public function create(User $user): bool
    {
        return in_array($user->tipo_usuario, ['admin', 'gestor', 'abogado']);
    }

    /**
     * Determina si el usuario puede actualizar una persona.
     */
    public function update(User $user, Persona $persona): bool
    {
        return in_array($user->tipo_usuario, ['admin', 'gestor', 'abogado']);
    }

    /**
     * Determina si el usuario puede eliminar una persona.
     * Solo los administradores tienen esta autoridad.
     */
    public function delete(User $user, ?Persona $persona = null): bool
    {
        return $user->tipo_usuario === 'admin';
    }
}
