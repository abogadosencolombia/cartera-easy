<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subproceso extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     * Le decimos a Eloquent que use la tabla 'subprocesos'.
     */
    protected $table = 'subprocesos';

    /**
     * The attributes that are mass assignable.
     * Esto permite que el Seeder funcione.
     */
    protected $fillable = [
        'nombre',
        'subtipo_proceso_id',
    ];

    /**
     * Define la relación inversa: Un Subproceso (L4) pertenece a un SubtipoProceso (L3).
     */
    public function subtipoProceso(): BelongsTo
    {
        // Apunta al modelo SubtipoProceso
        return $this->belongsTo(SubtipoProceso::class, 'subtipo_proceso_id');
    }
}

