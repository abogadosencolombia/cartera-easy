<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne; 
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\RevisionDiaria;
use App\Models\Contrato; 
use App\Models\Tarea;
use App\Models\EtapaProcesal; 
use App\Models\AuditoriaEvento;

use Illuminate\Database\Eloquent\SoftDeletes;

class ProcesoRadicado extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'proceso_radicados';

    protected $appends = ['demandante', 'demandado'];

    protected $fillable = [
        'radicado','fecha_radicado','naturaleza','asunto','correo_radicacion',
        'fecha_revision','fecha_proxima_revision','ultima_actuacion','link_expediente',
        'ubicacion_drive','correos_juzgado','observaciones','created_by',
        'abogado_id','responsable_revision_id','juzgado_id','tipo_proceso_id',
        'estado', 'nota_cierre', 'info_incompleta',
        'etapa_procesal_id', 
        'fecha_cambio_etapa',
        'is_pinned',
        'checklist_seguimiento',
        'a_favor_de'
    ];

    /**
     * Mutator para normalizar el radicado.
     * Si el radicado tiene 23 dígitos (o similar con ruido), limpiamos espacios y guiones.
     */
    protected function radicado(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            set: function ($value) {
                if (empty($value)) return null;
                $clean = preg_replace('/[^0-9]/', '', $value);
                // Si al limpiar quedan 23 dígitos, usamos el limpio. 
                // Si no, dejamos el original por si es un radicado antiguo/diferente.
                return (strlen($clean) === 23) ? $clean : trim($value);
            }
        );
    }

    protected $casts = [
        'fecha_radicado' => 'date',
        'fecha_revision' => 'date',
        'fecha_proxima_revision' => 'date',
        'fecha_cambio_etapa' => 'datetime',
        'info_incompleta' => 'boolean',
        'is_pinned' => 'boolean',
        'checklist_seguimiento' => 'array',
    ];

    /**
     * Boot del modelo para automatizar tareas.
     */
    protected static function boot()
    {
        parent::boot();

        // Al actualizar cualquier campo del radicado, marcamos como revisado HOY
        static::updating(function ($proceso) {
            // Solo actualizamos si no se está intentando cambiar la fecha de revisión explícitamente
            if (!$proceso->isDirty('fecha_revision')) {
                $proceso->fecha_revision = now();
            }
        });
    }

    /**
     * Fuerza la actualización de la fecha de revisión al momento actual.
     */
    public function touchRevision()
    {
        $this->update(['fecha_revision' => now()]);
    }

    // --- RELACIONES ---

    public function abogado(): BelongsTo { return $this->belongsTo(User::class, 'abogado_id'); }
    
    public function responsableRevision(): BelongsTo { return $this->belongsTo(User::class, 'responsable_revision_id'); }

    // ALIAS PARA EVITAR ERROR 500
    public function responsable(): BelongsTo { return $this->belongsTo(User::class, 'responsable_revision_id'); }

    // RELACIÓN CREADOR (Necesaria para el Excel)
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function juzgado(): BelongsTo { return $this->belongsTo(Juzgado::class, 'juzgado_id'); }
    public function tipoProceso(): BelongsTo { return $this->belongsTo(TipoProceso::class, 'tipo_proceso_id'); }

    public function personas(): BelongsToMany
    {
        return $this->belongsToMany(Persona::class, 'proceso_radicado_personas')
                    ->withPivot('tipo')
                    ->withTrashed()
                    ->withTimestamps();
    }

    public function demandantes(): BelongsToMany
    {
        return $this->belongsToMany(Persona::class, 'proceso_radicado_personas')
                    ->wherePivot('tipo', 'DEMANDANTE')
                    ->withTrashed()
                    ->withTimestamps();
    }

    public function demandados(): BelongsToMany
    {
        return $this->belongsToMany(Persona::class, 'proceso_radicado_personas')
                    ->wherePivot('tipo', 'DEMANDADO')
                    ->withTrashed()
                    ->withTimestamps();
    }
    
    public function getDemandanteAttribute() { return $this->demandantes->first(); }
    public function getDemandadoAttribute() { return $this->demandados->first(); }

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoProceso::class, 'proceso_radicado_id');
    }

    public function notificaciones(): HasMany
    {
        return $this->hasMany(NotificacionCaso::class, 'proceso_id');
    }

    public function actuaciones(): MorphMany
    {
        return $this->morphMany(Actuacion::class, 'actuable')->orderBy('created_at', 'desc');
    }

    public function revisionesDiarias(): MorphMany
    {
        return $this->morphMany(RevisionDiaria::class, 'revisable');
    }

    public function contrato(): HasOne
    {
        return $this->hasOne(Contrato::class, 'proceso_id');
    }

    public function tareas(): MorphMany
    {
        return $this->morphMany(Tarea::class, 'tarea');
    }

    public function auditoria(): MorphMany
    {
        return $this->morphMany(AuditoriaEvento::class, 'auditable')->latest();
    }

    // --- LÓGICA INTELIGENTE ---
    public function etapaActual(): BelongsTo
    {
        return $this->belongsTo(EtapaProcesal::class, 'etapa_procesal_id');
    }

    public function getSemaforoRiesgoAttribute()
    {
        if (!$this->etapaActual) return 'gray';
        return match($this->etapaActual->riesgo) {
            'MUY_ALTO' => 'red',
            'ALTO'     => 'orange',
            'MEDIO'    => 'yellow',
            default    => 'green',
        };
    }

    public function getDiasParaVencerAttribute()
    {
        if (!$this->etapaActual || !$this->fecha_cambio_etapa || $this->etapaActual->sla_dias == 0) {
            return null; 
        }
        $fechaLimite = $this->fecha_cambio_etapa->copy()->addWeekdays($this->etapaActual->sla_dias);
        return now()->diffInDays($fechaLimite, false);
    }
}