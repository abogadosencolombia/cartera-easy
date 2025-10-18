<?php

namespace App\Policies;

use App\Models\PlantillaDocumento;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PlantillaPolicy
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
     * Determina si el usuario puede ver el listado de plantillas.
     */
    public function viewAny(User $user): bool
    {
        // Solo los roles internos pueden gestionar plantillas.
        return in_array($user->tipo_usuario, ['admin', 'gestor', 'abogado']);
    }

    /**
     * Determina si el usuario puede ver una plantilla específica.
     * La lógica es: si puedes ver el listado, puedes ver una plantilla individual.
     * La seguridad real se aplica al ver las de tu cooperativa.
     */
    public function view(User $user, PlantillaDocumento $plantillaDocumento): bool
    {
        if (in_array($user->tipo_usuario, ['gestor', 'abogado'])) {
            return $user->cooperativas->contains($plantillaDocumento->cooperativa_id);
        }
        // El admin ya tiene acceso por el método 'before'.
        return false;
    }

    /**
     * Determina si el usuario puede crear plantillas.
     * Solo el Alto Mando (admin) puede añadir nuevas armas al arsenal.
     */
    public function create(User $user): bool
    {
        return $user->tipo_usuario === 'admin';
    }

    /**
     * Determina si el usuario puede actualizar una plantilla.
     * Solo el Alto Mando (admin) puede modificar las plantillas maestras.
     */
    public function update(User $user, PlantillaDocumento $plantillaDocumento): bool
    {
        return $user->tipo_usuario === 'admin';
    }

    /**
     * Determina si el usuario puede eliminar una plantilla.
     * Solo el Alto Mando (admin) puede dar de baja una plantilla.
     */
    public function delete(User $user, PlantillaDocumento $plantillaDocumento): bool
    {
        return $user->tipo_usuario === 'admin';
    }
}
