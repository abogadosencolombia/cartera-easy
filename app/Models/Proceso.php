<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Proceso extends Model
{
    // Nombre correcto de la tabla
    protected $table = 'proceso_radicados'; 

    protected $fillable = [
        'abogado', // OJO: Si esto es relación con User, debería ser 'abogado_id' o similar, verifica tu BD.
        'radicado',
        'fecha_radicado',
        'juzgado_entidad',
        'naturaleza',
        'tipo_proceso',
        'asunto',
        // 'demandante',  <-- ELIMINADO: No existe columna, es relación pivot
        // 'demandado_id', <-- ELIMINADO: No existe columna, es relación pivot
        'correo_radicacion',
        'fecha_revision',
        'responsable_revision',
        'fecha_proxima_revision',
        'observaciones',
        'ultima_actuacion',
        'link_expediente',
        'correos_juzgado',
        'ubicacion_drive',
        'created_by',
    ];

    protected $casts = [
        'fecha_radicado'         => 'date',
        'fecha_revision'         => 'date',
        'fecha_proxima_revision' => 'date',
    ];
    
    // ✅ Relación inversa Muchos a Muchos (Opcional, pero útil)
    public function demandados(): BelongsToMany
    {
        return $this->belongsToMany(Persona::class, 'proceso_radicado_personas', 'proceso_radicado_id', 'persona_id')
                    ->wherePivot('tipo', 'DEMANDADO');
    }

    public function demandantes(): BelongsToMany
    {
        return $this->belongsToMany(Persona::class, 'proceso_radicado_personas', 'proceso_radicado_id', 'persona_id')
                    ->wherePivot('tipo', 'DEMANDANTE');
    }
}