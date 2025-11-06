<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne; // <-- Añadido para la relación con Contrato
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\RevisionDiaria;
use App\Models\Contrato; // <-- Añadido para referenciar el modelo Contrato

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

    /**
     * Get all of the radicado's actuations.
     * Se ordenan de la más reciente a la más antigua por defecto.
     */
    public function actuaciones(): MorphMany
    {
        return $this->morphMany(Actuacion::class, 'actuable')->orderBy('created_at', 'desc');
    }

    /**
     * Obtiene todas las revisiones diarias para este radicado.
     */
    public function revisionesDiarias(): MorphMany
    {
        return $this->morphMany(RevisionDiaria::class, 'revisable');
    }

    // ===== NUEVA RELACIÓN AÑADIDA =====
    /**
     * Obtiene el contrato asociado a este radicado (si existe).
     */
    public function contrato(): HasOne
    {
        // Asume que la tabla 'contratos' tiene una columna 'proceso_id'
        // que es la llave foránea hacia 'proceso_radicados.id'
        return $this->hasOne(Contrato::class, 'proceso_id');
    }
    // ===================================
}
