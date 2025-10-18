<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntegracionExternaLog extends Model
{
    use HasFactory;

    /**
     * La lista de atributos que se pueden asignar masivamente.
     * Esto le da permiso a Laravel para rellenar estos campos usando el método create().
     */
    protected $fillable = [
        'user_id',
        'servicio',
        'endpoint',
        'datos_enviados',
        'respuesta',
        'fecha_solicitud',
    ];

    // Es una buena práctica mantener la definición explícita de la tabla,
    // aunque Laravel a menudo puede inferirla.
    protected $table = 'integracion_externa_logs';
}