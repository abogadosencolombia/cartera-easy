<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

// ===== INICIO DE LA MODIFICACIÓN (PASO 1) =====
// Esta es la línea que faltaba para que el auto-borrado
// de notificaciones funcione correctamente.
use Illuminate\Notifications\DatabaseNotification;
// ===== FIN DE LA MODIFICACIÓN =====

class Tarea extends Model
{
    use HasFactory;

    protected $fillable = [
        'titulo',
        'descripcion',
        'admin_id',
        'user_id',
        'tarea_type',
        'tarea_id',
        'estado',
        'fecha_completado',
    ];

    protected $casts = [
        'fecha_completado' => 'datetime',
    ];

    /**
     * Obtiene el elemento al que esta vinculada la tarea (Proceso, Caso, Contrato).
     */
    public function tarea(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Obtiene el usuario al que se le asignó la tarea.
     */
    public function asignadoA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Obtiene el administrador que creó la tarea.
     */
    public function creadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    protected static function booted(): void
    {
        /**
         * Escucha el evento 'deleting' (cuando se llama a $tarea->delete()).
         * Esto buscará y eliminará las notificaciones huérfanas
         * asociadas a esta tarea para que no aparezcan en la UI.
         */
        static::deleting(function (Tarea $tarea) {
            // Buscamos en la tabla 'notifications'
            // Esta línea ahora funcionará gracias a la importación
            DatabaseNotification::where('type', 'App\Notifications\NuevaTareaAsignada')
                ->where('data', 'like', '%"tarea_id":'.$tarea->id.'%')
                ->delete();
        });
    }
}