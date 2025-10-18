<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcesoRadicado extends Model
{
    use HasFactory;

    protected $table = 'proceso_radicados';

    protected $fillable = [
        'radicado','fecha_radicado','naturaleza','asunto','correo_radicacion',
        'fecha_revision','fecha_proxima_revision','ultima_actuacion','link_expediente',
        'ubicacion_drive','correos_juzgado','observaciones','created_by',
        'abogado_id','responsable_revision_id','juzgado_id','tipo_proceso_id',
        'demandante_id','demandado_id',
        // --- CAMPOS AÑADIDOS ---
        'estado', 'nota_cierre',
    ];

    protected $casts = [
        'fecha_radicado' => 'date',
        'fecha_revision' => 'date',
        'fecha_proxima_revision' => 'date',
    ];

    public function abogado(): BelongsTo { return $this->belongsTo(User::class, 'abogado_id'); }
    public function responsableRevision(): BelongsTo { return $this->belongsTo(User::class, 'responsable_revision_id'); }
    public function juzgado(): BelongsTo { return $this->belongsTo(Juzgado::class, 'juzgado_id'); }
    public function tipoProceso(): BelongsTo { return $this->belongsTo(TipoProceso::class, 'tipo_proceso_id'); }
    public function demandante(): BelongsTo { return $this->belongsTo(Persona::class, 'demandante_id'); }
    public function demandado(): BelongsTo { return $this->belongsTo(Persona::class, 'demandado_id'); }

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoProceso::class, 'proceso_radicado_id');
    }
}
