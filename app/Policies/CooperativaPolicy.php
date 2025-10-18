<?php

namespace App\Policies;

use App\Models\Cooperativa;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CooperativaPolicy
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
     * Todos los usuarios autenticados pueden ver el listado general de cooperativas.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determina si el usuario puede ver una cooperativa específica.
     * NUEVA LÓGICA: Verifica si la cooperativa está en la lista de cooperativas del usuario.
     */
    public function view(User $user, Cooperativa $cooperativa): bool
    {
        return $user->cooperativas->contains($cooperativa);
    }

    /**
     * Solo los administradores pueden crear nuevas cooperativas.
     */
    public function create(User $user): bool
    {
        return $user->tipo_usuario === 'admin';
    }

    /**
     * Determina si el usuario puede actualizar una cooperativa.
     * NUEVA LÓGICA: Verifica si la cooperativa está en la lista de cooperativas del usuario.
     */
    public function update(User $user, Cooperativa $cooperativa): bool
    {
        return $user->cooperativas->contains($cooperativa);
    }

    /**
     * Solo los administradores pueden eliminar cooperativas.
     */
    public function delete(User $user, ?Cooperativa $cooperativa = null): bool
    {
        return $user->tipo_usuario === 'admin';
    }
}
