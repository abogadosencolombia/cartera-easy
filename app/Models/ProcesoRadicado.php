<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne; 
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany; // <-- Importante
use App\Models\RevisionDiaria;
use App\Models\Contrato; 
use App\Models\Tarea;

class ProcesoRadicado extends Model
{
    use HasFactory;

    protected $table = 'proceso_radicados';

    protected $fillable = [
        'radicado','fecha_radicado','naturaleza','asunto','correo_radicacion',
        'fecha_revision','fecha_proxima_revision','ultima_actuacion','link_expediente',
        'ubicacion_drive','correos_juzgado','observaciones','created_by',
        'abogado_id','responsable_revision_id','juzgado_id','tipo_proceso_id',
        // 'demandante_id', 'demandado_id', // <-- Estos ya no se usan directamente
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

    // ===== NUEVAS RELACIONES MUCHOS A MUCHOS =====
    
    public function demandantes(): BelongsToMany
    {
        return $this->belongsToMany(Persona::class, 'proceso_radicado_personas')
                    ->wherePivot('tipo', 'DEMANDANTE')
                    ->withTimestamps();
    }

    public function demandados(): BelongsToMany
    {
        return $this->belongsToMany(Persona::class, 'proceso_radicado_personas')
                    ->wherePivot('tipo', 'DEMANDADO')
                    ->withTimestamps();
    }
    
    // --- Helpers para compatibilidad (opcional, útil para exportaciones) ---
    // Devuelve el primero de la lista para casos donde solo se necesite uno
    public function getDemandanteAttribute() { return $this->demandantes->first(); }
    public function getDemandadoAttribute() { return $this->demandados->first(); }

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoProceso::class, 'proceso_radicado_id');
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
}