<?php

namespace App\Traits;

use App\Models\RevisionDiaria;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

trait RegistraRevisionTrait
{
    /**
     * Registra automáticamente una revisión diaria para el ítem actual.
     * Solo si el usuario tiene roles de gestión (admin, gestor, abogado).
     */
    protected function registrarRevisionAutomatica($model)
    {
        $user = Auth::user();
        
        if (!$user || !in_array($user->tipo_usuario, ['admin', 'gestor', 'abogado'])) {
            return;
        }

        // Si el modelo es nulo o no tiene ID, no hacemos nada
        if (!$model || !$model->id) {
            return;
        }

        $hoy = Carbon::today();

        // Usamos updateOrCreate para asegurar que la fecha de actualización cambie
        // y que no haya duplicados para el mismo usuario/item el mismo día.
        RevisionDiaria::updateOrCreate(
            [
                'user_id' => $user->id,
                'fecha_revision' => $hoy,
                'revisable_id' => $model->id,
                'revisable_type' => get_class($model),
            ],
            [
                'updated_at' => now(),
            ]
        );
    }
}
