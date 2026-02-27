<?php

namespace App\Policies;

use App\Models\ProcesoRadicado;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProcesoRadicadoPolicy
{
    use HandlesAuthorization;

    /**
     * Permite a un admin realizar CUALQUIER acción.
     * Esto es un "catch-all" para administradores.
     */
    public function before(User $user, string $ability): bool|null
    {
        if ($user->tipo_usuario === 'admin') {
            return true;
        }

        return null; // Dejar que las otras reglas decidan
    }

    /**
     * Determina si el usuario puede ver la lista de radicados.
     */
    public function viewAny(User $user): bool
    {
        // Basado en tu regla: "cualquier abogado, gestor puede ver los radicados"
        return in_array($user->tipo_usuario, ['gestor', 'abogado']);
    }

    /**
     * Determina si el usuario puede ver un radicado específico.
     * * ¡ESTA ES LA FUNCIÓN QUE SOLUCIONA TU ERROR 403!
     * */
    public function view(User $user, ProcesoRadicado $procesoRadicado): bool
    {
        // Basado en tu regla: "cualquier abogado, gestor puede ver los radicados"
        return in_array($user->tipo_usuario, ['gestor', 'abogado']);
    }

    /**
     * Determina si el usuario puede crear un radicado.
     * (Asumimos que gestores y abogados pueden)
     */
    public function create(User $user): bool
    {
        return in_array($user->tipo_usuario, ['gestor', 'abogado']);
    }

    /**
     * Determina si el usuario puede actualizar un radicado.
     * (Asumimos que gestores y abogados pueden)
     */
    public function update(User $user, ProcesoRadicado $procesoRadicado): bool
    {
        return in_array($user->tipo_usuario, ['gestor', 'abogado']);
    }

    /**
     * Determina si el usuario puede eliminar un radicado.
     */
    public function delete(User $user, ProcesoRadicado $procesoRadicado): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determina si el usuario puede restaurar un radicado eliminado.
     */
    public function restore(User $user, ProcesoRadicado $procesoRadicado): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determina si el usuario puede eliminar permanentemente un radicado.
     */
    public function forceDelete(User $user, ProcesoRadicado $procesoRadicado): bool
    {
        return $user->isAdmin();
    }
}