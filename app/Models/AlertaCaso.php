<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertaCaso extends Model
{
    use HasFactory;

    protected $table = 'alertas_caso';

    protected $fillable = [
        'caso_id',
        'tipo_alerta',
        'descripcion',
        'leida',
    ];

    protected $casts = [
        'leida' => 'boolean',
    ];

    /**
     * Una alerta pertenece a un único Caso.
     */
    public function caso(): BelongsTo
    {
        return $this->belongsTo(Caso::class);
    }
}
