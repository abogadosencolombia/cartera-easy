<?php

namespace App\Policies;

use App\Models\User;

class ReportPolicy
{
    /**
     * Determina si un usuario puede ver el dashboard de reportes.
     */
    public function viewDashboard(User $user): bool
    {
        // Solo los usuarios internos (no clientes) pueden ver los reportes.
        return in_array($user->tipo_usuario, ['admin', 'gestor', 'abogado']);
    }

    /**
     * Determina si un usuario puede ver los datos de todas las cooperativas.
     */
    public function viewAllData(User $user): bool
    {
        // Solo el administrador puede ver los datos consolidados de todas las cooperativas.
        return $user->tipo_usuario === 'admin';
    }
}
