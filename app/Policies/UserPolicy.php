<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Perform pre-authorization checks.
     * ESTA ES LA REGLA MAESTRA. Se ejecuta antes que cualquier otra regla.
     */
    public function before(User $user, string $ability): bool|null
    {
        // Si el usuario tiene el tipo 'admin', se le concede permiso para TODO y no se revisa nada más.
        if ($user->tipo_usuario === 'admin') {
            return true;
        }

        return null; // Si no es admin, continúa con las otras reglas.
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Por defecto, cualquier usuario autenticado puede ver la lista,
        // pero la regla 'before' ya le dio acceso al admin.
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // Un usuario puede ver su propio perfil o si es admin (cubierto por 'before').
        return $user->id === $model->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Solo los admins pueden crear usuarios (cubierto por 'before').
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // Un usuario puede actualizar su propio perfil o si es admin (cubierto por 'before').
        return $user->id === $model->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Nadie puede borrarse a sí mismo.
        if ($user->id === $model->id) {
            return false;
        }
        // Solo los admins pueden borrar a otros (cubierto por 'before').
        return false;
    }
}
