<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Carbon\Carbon;
use App\Models\RevisionDiaria;
use App\Models\ProcesoRadicado;
use App\Models\Tarea;
// ✅ IMPORTANTE: Importar los modelos hijos
use App\Models\ContratoCuota; 
use App\Models\ContratoCargo; 
use App\Models\ContratoPago;  

class Contrato extends Model
{
    use HasFactory;

    protected $table = 'contratos';

    protected $fillable = [
        'cliente_id',
        'monto_total',
        'anticipo',
        'porcentaje_litis',
        'monto_base_litis',
        'litis_valor_ganado',
        'modalidad',
        'frecuencia_pago', // <--- NUEVO CAMPO AÑADIDO
        'estado',
        'inicio',
        'nota',
        'contrato_origen_id',
        'documento_contrato',
        'proceso_id', 
        'caso_id',
    ];

    protected $casts = [
        'inicio' => 'date',
        'monto_total' => 'decimal:2',
        'anticipo' => 'decimal:2',
        'porcentaje_litis' => 'decimal:2',
        'monto_base_litis' => 'decimal:2',
        'litis_valor_ganado' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'cliente_id')->withTrashed();
    }

    public function contratoOrigen(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'contrato_origen_id');
    }

    /**
     * Relación con las cuotas del contrato.
     */
    public function cuotas(): HasMany
    {
        return $this->hasMany(ContratoCuota::class, 'contrato_id')->orderBy('numero');
    }

    /**
     * Relación con los cargos adicionales del contrato.
     */
    public function cargos(): HasMany
    {
        return $this->hasMany(ContratoCargo::class, 'contrato_id')->orderByDesc('fecha_aplicado');
    }

    /**
     * Relación con los pagos registrados para el contrato.
     */
    public function pagos(): HasMany
    {
        return $this->hasMany(ContratoPago::class, 'contrato_id')->orderByDesc('fecha');
    }

    public function actuaciones(): MorphMany
    {
        return $this->morphMany(Actuacion::class, 'actuable')->latest();
    }

    public function revisionesDiarias(): MorphMany
    {
        return $this->morphMany(RevisionDiaria::class, 'revisable');
    }

    public function proceso(): BelongsTo
    {
        return $this->belongsTo(ProcesoRadicado::class, 'proceso_id');
    }

    public function caso(): BelongsTo
    {
        return $this->belongsTo(Caso::class, 'caso_id');
    }

    public function tareas(): MorphMany
    {
        return $this->morphMany(Tarea::class, 'tarea');
    }
}