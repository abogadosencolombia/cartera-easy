<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Notifications\DatabaseNotification;

class Tarea extends Model
{
    use HasFactory;

    // EL PORTERO: Aquí agregamos 'fecha_limite' y 'aviso_vencimiento_enviado'
    protected $fillable = [
        'titulo',
        'descripcion',
        'admin_id',
        'user_id',
        'tarea_type',
        'tarea_id',
        'estado',
        'fecha_completado',
        'fecha_limite',             // <--- ¡IMPORTANTE! Sin esto, no se guarda.
        'aviso_vencimiento_enviado' // <--- ¡IMPORTANTE!
    ];

    protected $casts = [
        'fecha_completado' => 'datetime',
        'fecha_limite' => 'datetime', // Para que Carbon maneje las fechas
        'aviso_vencimiento_enviado' => 'boolean',
    ];

    protected $appends = ['semaforo'];

    /**
     * Semáforo lógico para el Frontend
     */
    public function getSemaforoAttribute()
    {
        if ($this->estado === 'completada') {
            return 'completado'; // Azul
        }

        if (!$this->fecha_limite) {
            return 'sin_fecha'; // Gris
        }

        $ahora = now();

        if ($ahora->greaterThan($this->fecha_limite)) {
            return 'vencida'; // Rojo
        }

        if ($ahora->diffInHours($this->fecha_limite) < 24) {
            return 'urgente'; // Amarillo
        }

        return 'tiempo'; // Verde
    }

    public function tarea(): MorphTo
    {
        return $this->morphTo();
    }

    public function asignadoA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    protected static function booted(): void
    {
        static::deleting(function (Tarea $tarea) {
            DatabaseNotification::where('type', 'App\Notifications\NuevaTareaAsignada')
                ->where('data', 'like', '%"tarea_id":'.$tarea->id.'%')
                ->delete();
        });
    }
}