<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialValidacionLegal extends Model
{
    use HasFactory;

    protected $table = 'historial_validacion_legal';

    protected $fillable = [
        'validacion_legal_id',
        'estado_anterior',
        'estado_nuevo',
        'user_id',
        'comentario',
    ];

    /**
     * Obtiene la validación a la que pertenece este registro de historial.
     */
    public function validacionLegal(): BelongsTo
    {
        return $this->belongsTo(ValidacionLegal::class);
    }

    /**
     * Obtiene el usuario que realizó el cambio.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}