<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReglaAlerta extends Model
{
    use HasFactory;

    protected $table = 'reglas_alerta';

    protected $fillable = [
        'cooperativa_id',
        'tipo',
        'dias',
        'activa',
    ];

    protected $casts = [
        'activa' => 'boolean',
    ];

    /**
     * Una regla pertenece a una Cooperativa.
     */
    public function cooperativa(): BelongsTo
    {
        return $this->belongsTo(Cooperativa::class);
    }
}
