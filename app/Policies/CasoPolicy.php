<?php

namespace App\Policies;

use App\Models\Caso;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CasoPolicy
{
    /**
     * Determina si el usuario puede ver el listado de casos.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determina si el usuario puede ver un caso específico.
     */
    public function view(User $user, Caso $caso): bool
    {
        if ($user->tipo_usuario === 'admin') {
            return true;
        }
        if (in_array($user->tipo_usuario, ['gestor', 'abogado'])) {
            return $user->cooperativas->contains($caso->cooperativa_id)
                || (int) $caso->user_id === (int) $user->id
                || $caso->users()->where('users.id', $user->id)->exists();
        }
        if ($user->tipo_usuario === 'cliente') {
            if ((int) $user->persona_id === (int) $caso->deudor_id) {
                return true;
            }

            $documento = $user->persona?->numero_documento;
            return filled($documento)
                && $caso->codeudores()->where('numero_documento', $documento)->exists();
        }
        return false;
    }

    /**
     * Determina si el usuario puede crear casos.
     */
    public function create(User $user): bool
    {
        return in_array($user->tipo_usuario, ['admin', 'gestor', 'abogado']);
    }

    /**
     * Determina si el usuario puede actualizar un caso.
     */
    public function update(User $user, Caso $caso): bool
    {
        // Un admin siempre puede actualizar, incluso para desbloquear un caso.
        if ($user->tipo_usuario === 'admin') {
            return true;
        }

        // LÓGICA DE BLINDAJE: Si el caso está bloqueado, ningún otro rol puede editarlo.
        if ($caso->bloqueado) {
            return false;
        }

        // Un gestor/abogado puede actualizar un caso (si no está bloqueado) si pertenece a una de sus cooperativas.
        if (in_array($user->tipo_usuario, ['gestor', 'abogado'])) {
            return $user->cooperativas->contains($caso->cooperativa_id)
                || (int) $caso->user_id === (int) $user->id
                || $caso->users()->where('users.id', $user->id)->exists();
        }

        return false;
    }

    /**
     * Determina si el usuario puede eliminar un caso.
     */
    public function delete(User $user, ?Caso $caso = null): bool
    {
        return $user->tipo_usuario === 'admin';
    }
}
