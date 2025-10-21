<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditoriaEvento extends Model
{
    use HasFactory;

    protected $table = 'auditoria_eventos';

    protected $fillable = [
        'user_id',
        'evento',
        'descripcion_breve',
        'auditable_id',
        'auditable_type',
        'criticidad',
        'detalle_anterior',
        'detalle_nuevo',
        'direccion_ip',
        'user_agent',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'detalle_anterior' => 'array',
        'detalle_nuevo' => 'array',
    ];

    /**
     * Get the user that performed the action.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the parent auditable model (Caso, Documento, etc.).
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
