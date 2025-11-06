<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // <-- Importado
use Illuminate\Database\Eloquent\Relations\HasMany;   // <-- Importado

class TipoProceso extends Model
{
    use HasFactory;

    // Tu tabla de L2 (según tu modelo anterior)
    protected $table = 'tipos_proceso';

    protected $fillable = [
        'nombre',
        'descripcion',
        'especialidad_juridica_id', // <-- Llave foránea a L1
    ];

    /**
     * RELACIÓN L2 -> L1
     * Un Tipo de Proceso pertenece a una Especialidad.
     */
    public function especialidad(): BelongsTo
    {
        return $this->belongsTo(EspecialidadJuridica::class, 'especialidad_juridica_id');
    }

    /**
     * RELACIÓN L2 -> L3
     * Un Tipo de Proceso tiene muchos Subtipos.
     */
    public function subtipos(): HasMany
    {
        // Esta es la relación que carga el Nivel 3
        return $this->hasMany(SubtipoProceso::class, 'tipo_proceso_id');
    }
}

