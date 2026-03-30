<?php

namespace App\Policies;

use App\Models\ProcesoRadicado;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProcesoRadicadoPolicy
{
    use HandlesAuthorization;

    /**
     * Cooperativa exclusiva para Radicados: ID 1
     */
    protected const COOP_RADICADOS_ID = 1;

    /**
     * Permite a un admin realizar CUALQUIER acción.
     */
    public function before(User $user, string $ability): bool|null
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null; 
    }

    /**
     * Valida si el usuario pertenece a la cooperativa de radicados (ID 1).
     */
    private function belongsToRadicadosCoop(User $user): bool
    {
        return $user->cooperativas()->where('cooperativas.id', self::COOP_RADICADOS_ID)->exists();
    }

    /**
     * Determina si el usuario puede ver la lista de radicados.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->tipo_usuario, ['gestor', 'abogado']);
    }

    /**
     * Determina si el usuario puede ver un radicado específico.
     */
    public function view(User $user, ProcesoRadicado $procesoRadicado): bool
    {
        return in_array($user->tipo_usuario, ['gestor', 'abogado']);
    }

    /**
     * Determina si el usuario puede crear un radicado.
     */
    public function create(User $user): bool
    {
        return in_array($user->tipo_usuario, ['gestor', 'abogado']);
    }

    /**
     * Determina si el usuario puede actualizar un radicado.
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
}
