<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proceso extends Model
{
    protected $table = 'procesos';

    protected $fillable = [
        'abogado',
        'radicado',
        'fecha_radicado',
        'juzgado_entidad',
        'naturaleza',
        'tipo_proceso',
        'asunto',
        'demandante',
        'demandado',
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
}
