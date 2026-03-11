<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoProceso extends Model
{
    use HasFactory;

    protected $table = 'documento_procesos';

    protected $fillable = [
        'proceso_radicado_id',
        'user_id',
        'descripcion',   // texto opcional
        'file_path',     // ruta relative en storage
        'file_name',     // nombre mostrado
    ];

    /**
     * Boot del modelo para actualizar la fecha de revisión del proceso padre.
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($documento) {
            $documento->proceso?->touchRevision();
        });

        static::deleted(function ($documento) {
            $documento->proceso?->touchRevision();
        });
    }

    public function proceso(): BelongsTo
    {
        return $this->belongsTo(ProcesoRadicado::class, 'proceso_radicado_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
