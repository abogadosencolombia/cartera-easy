<?php

namespace App\Observers;

use App\Models\PagoCaso;

class PagoCasoObserver
{
    /**
     * Handle the PagoCaso "created" event.
     * Se ejecuta automáticamente después de que un nuevo pago es creado.
     */
    public function created(PagoCaso $pagoCaso): void
    {
        // Actualizamos la fecha del último pago en el caso relacionado.
        $caso = $pagoCaso->caso;
        $caso->fecha_ultimo_pago = $pagoCaso->fecha_pago;
        $caso->save();
    }
}
