<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // <-- Importado
use Illuminate\Database\Eloquent\Relations\HasMany;   // <-- Importado

class SubtipoProceso extends Model
{
    use HasFactory;

    // Tu tabla de L3 (según tu modelo anterior)
    protected $table = 'subtipos_proceso';
    
    protected $fillable = ['tipo_proceso_id','nombre','descripcion'];

    /**
     * RELACIÓN L3 -> L2
     * Un Subtipo de Proceso pertenece a un Tipo.
     */
    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoProceso::class, 'tipo_proceso_id');
    }

    /**
     * =================================================================
     * ¡NUEVA RELACIÓN L3 -> L4!
     * Un Subtipo de Proceso tiene muchos Subprocesos (los detalles).
     * =================================================================
     */
    public function subprocesos(): HasMany
    {
        // Esta es la relación que carga el Nivel 4
        return $this->hasMany(Subproceso::class, 'subtipo_proceso_id');
    }
}

