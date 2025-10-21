<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialMora extends Model
{
    use HasFactory;

    protected $table = 'historial_mora';

    protected $fillable = [
        'caso_id',
        'fecha_inicio_mora',
        'fecha_fin_mora',
        'dias_en_mora',
    ];

    protected $casts = [
        'fecha_inicio_mora' => 'date',
        'fecha_fin_mora' => 'date',
    ];

    /**
     * Un registro de historial de mora pertenece a un único Caso.
     */
    public function caso(): BelongsTo
    {
        return $this->belongsTo(Caso::class);
    }
}
