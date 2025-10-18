<?php

namespace App\Policies;

use App\Models\DocumentoGenerado;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DocumentoGeneradoPolicy
{
    /**
     * Determina si el usuario puede ver el listado de documentos generados.
     */
    public function viewAny(User $user): bool
    {
        // Solo los usuarios internos pueden acceder a la auditoría de documentos.
        return in_array($user->tipo_usuario, ['admin', 'gestor', 'abogado']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DocumentoGenerado $documentoGenerado): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DocumentoGenerado $documentoGenerado): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DocumentoGenerado $documentoGenerado): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, DocumentoGenerado $documentoGenerado): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, DocumentoGenerado $documentoGenerado): bool
    {
        return false;
    }
}
