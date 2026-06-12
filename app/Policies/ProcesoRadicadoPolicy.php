<?php

namespace App\Policies;

use App\Models\ProcesoRadicado;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProcesoRadicadoPolicy
{
    use HandlesAuthorization;

    /**
     * Cooperativa exclusiva para Radicados: ID 1.
     */
    protected const COOP_RADICADOS_ID = 1;

    public function before(User $user, string $ability): bool|null
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    private function belongsToRadicadosCoop(User $user): bool
    {
        return $user->cooperativas()->where('cooperativas.id', self::COOP_RADICADOS_ID)->exists();
    }

    private function hasBaseAccess(User $user): bool
    {
        return in_array($user->tipo_usuario, ['gestor', 'abogado'], true)
            && $this->belongsToRadicadosCoop($user);
    }

    private function isAssigned(User $user, ProcesoRadicado $procesoRadicado): bool
    {
        return (int) $procesoRadicado->abogado_id === (int) $user->id
            || (int) $procesoRadicado->responsable_revision_id === (int) $user->id
            || (int) $procesoRadicado->created_by === (int) $user->id;
    }

    public function viewAny(User $user): bool
    {
        return $this->hasBaseAccess($user);
    }

    public function view(User $user, ProcesoRadicado $procesoRadicado): bool
    {
        return in_array($user->tipo_usuario, ['gestor', 'abogado'], true)
            && ($this->belongsToRadicadosCoop($user) || $this->isAssigned($user, $procesoRadicado));
    }

    public function create(User $user): bool
    {
        return $this->hasBaseAccess($user);
    }

    public function update(User $user, ProcesoRadicado $procesoRadicado): bool
    {
        return in_array($user->tipo_usuario, ['gestor', 'abogado'], true)
            && ($this->belongsToRadicadosCoop($user) || $this->isAssigned($user, $procesoRadicado));
    }

    public function delete(User $user, ProcesoRadicado $procesoRadicado): bool
    {
        return $user->isAdmin();
    }
}
