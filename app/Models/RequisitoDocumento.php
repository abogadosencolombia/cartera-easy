<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequisitoDocumento extends Model
{
    use HasFactory;

    protected $table = 'requisitos_documento';

    /**
     * Los atributos que se pueden asignar de forma masiva.
     */
    protected $fillable = [
        'cooperativa_id',
        'tipo_proceso_id', // <-- CAMBIO: De 'tipo_proceso' a 'tipo_proceso_id'
        'tipo_documento_requerido',
    ];

    /**
     * Un requisito puede pertenecer a una Cooperativa.
     */
    public function cooperativa(): BelongsTo
    {
        return $this->belongsTo(Cooperativa::class);
    }

    /**
     * === NUEVA RELACIÓN ===
     * Un requisito pertenece a un Tipo de Proceso.
     */
    public function tipoProceso(): BelongsTo
    {
        return $this->belongsTo(TipoProceso::class);
    }
}
