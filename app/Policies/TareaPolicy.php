<?php

namespace App\Policies;

use App\Models\Tarea;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TareaPolicy
{
    use HandlesAuthorization;

    /**
     * Permite al admin ver la página de índice de tareas.
     */
    public function viewAny(User $user): bool
    {
        // Solo los admins pueden ver la lista de tareas
        return $user->tipo_usuario === 'admin';
    }

    /**
     * Permite al admin crear nuevas tareas.
     */
    public function create(User $user): bool
    {
        // Solo los admins pueden crear tareas
        return $user->tipo_usuario === 'admin';
    }

    /**
     * Permite al admin eliminar una tarea.
     */
    public function delete(User $user, Tarea $tarea): bool
    {
        // Solo los admins pueden eliminar tareas
        return $user->tipo_usuario === 'admin';
    }
}