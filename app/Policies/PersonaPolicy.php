<?php

namespace App\Policies;

use App\Models\Persona;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PersonaPolicy
{
    use HandlesAuthorization;

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
     * Valida si hay intersección entre las cooperativas del usuario y las de la persona.
     */
    private function sharesCooperativa(User $user, Persona $persona): bool
    {
        $userCoops = $user->cooperativas->pluck('id');
        return $persona->cooperativas()->whereIn('cooperativas.id', $userCoops)->exists();
    }

    /**
     * Determina si el usuario puede ver el listado de personas.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->tipo_usuario, ['gestor', 'abogado']);
    }

    /**
     * Determina si el usuario puede ver una persona específica.
     */
    public function view(User $user, Persona $persona): bool
    {
        return in_array($user->tipo_usuario, ['gestor', 'abogado']) && $this->sharesCooperativa($user, $persona);
    }

    /**
     * Determina si el usuario puede crear personas.
     */
    public function create(User $user): bool
    {
        return in_array($user->tipo_usuario, ['gestor', 'abogado']);
    }

    /**
     * Determina si el usuario puede actualizar una persona.
     */
    public function update(User $user, Persona $persona): bool
    {
        return in_array($user->tipo_usuario, ['gestor', 'abogado']) && $this->sharesCooperativa($user, $persona);
    }

    /**
     * Determina si el usuario puede "eliminar" (suspender) una persona.
     */
    public function delete(User $user, Persona $persona): bool
    {
        return in_array($user->tipo_usuario, ['gestor', 'abogado']) && $this->sharesCooperativa($user, $persona);
    }

    /**
     * Determina si el usuario puede restaurar una persona.
     */
    public function restore(User $user, Persona $persona): bool
    {
        return in_array($user->tipo_usuario, ['gestor', 'abogado']) && $this->sharesCooperativa($user, $persona);
    }
}
