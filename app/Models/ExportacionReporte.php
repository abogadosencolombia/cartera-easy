<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportacionReporte extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tipo_reporte',
        'filtros_aplicados',
    ];

    protected $casts = [
        'filtros_aplicados' => 'array',
    ];

    /**
     * Un registro de exportación pertenece a un Usuario.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
