<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotaGestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'descripcion',
        'despacho',
        'termino',
        'relacionable_id',
        'relacionable_type',
        'is_completed',
        'expires_at',
        'notified_before',
        'notified_after',
        'last_periodic_notification_at',
        'completed_at',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'expires_at' => 'datetime',
        'last_periodic_notification_at' => 'datetime',
        'completed_at' => 'datetime',
        'notified_before' => 'boolean',
        'notified_after' => 'boolean',
    ];

    protected $appends = ['semaforo', 'tiempo_restante'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function relacionable()
    {
        return $this->morphTo();
    }

    /**
     * Semáforo Visual
     * Verde/Azul: Hecho
     * Rojo: Vencido
     * Rojo Parpadeante: Menos de 1 hora
     * Naranja/Amarillo: Menos de 4 horas
     * Azul/Verde: Más de 4 horas
     */
    public function getSemaforoAttribute()
    {
        if ($this->is_completed) return 'success';

        $ahora = now();
        if ($ahora->greaterThan($this->expires_at)) return 'danger';

        $horasRestantes = $ahora->diffInHours($this->expires_at);

        if ($horasRestantes <= 1) return 'danger-blink'; 
        if ($horasRestantes <= 4) return 'warning'; 

        return 'info'; 
    }

    public function getTiempoRestanteAttribute()
    {
        if ($this->is_completed) return 'Completado';
        
        $ahora = now();
        if ($ahora->greaterThan($this->expires_at)) {
            return 'Vencido';
        }

        return $ahora->diffForHumans($this->expires_at, true);
    }
}
