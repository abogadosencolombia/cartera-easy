<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RevisionDiaria extends Model
{
    use HasFactory;

    protected $table = 'revisiones_diarias';

    protected $fillable = [
        'user_id',
        'fecha_revision',
        'revisable_id',
        'revisable_type',
    ];

    protected $casts = [
        'fecha_revision' => 'date',
    ];

    /**
     * Obtiene el usuario que realizó la revisión.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Obtiene el modelo padre (Caso, ProcesoRadicado o Contrato) de la revisión.
     */
    public function revisable(): MorphTo
    {
        return $this->morphTo();
    }
}
