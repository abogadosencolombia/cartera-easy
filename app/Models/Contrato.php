<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany; // Asegúrate que esté importado
use Carbon\Carbon; // Asegúrate que esté importado
// --- INICIO: AÑADIR IMPORT REVISION DIARIA ---
use App\Models\RevisionDiaria;
// --- FIN: AÑADIR IMPORT REVISION DIARIA ---

class Contrato extends Model
{
    use HasFactory;

    protected $table = 'contratos'; // Especificar tabla si no sigue convención

    protected $fillable = [
        'cliente_id',
        'monto_total',
        'anticipo',
        'porcentaje_litis',
        'monto_base_litis', // Añadido
        'litis_valor_ganado', // Añadido
        'modalidad',
        'estado',
        'inicio',
        'nota',
        'contrato_origen_id',
        'documento_contrato', // Añadido
        // Añadir otros campos si es necesario
    ];

    protected $casts = [
        'inicio' => 'date',
        'monto_total' => 'decimal:2',
        'anticipo' => 'decimal:2',
        'porcentaje_litis' => 'decimal:2',
        'monto_base_litis' => 'decimal:2', // Añadido
        'litis_valor_ganado' => 'decimal:2', // Añadido
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con el cliente (Persona).
     */
    public function cliente(): BelongsTo
    {
        // Asumiendo que existe el modelo Persona y la clave foránea es cliente_id
        return $this->belongsTo(Persona::class, 'cliente_id');
    }

    /**
     * Relación con el contrato original (si es una reestructuración).
     */
    public function contratoOrigen(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'contrato_origen_id');
    }

    // --- INICIO: Relaciones comentadas (Modelos relacionados no existen) ---
    /*
    /**
     * Relación con las cuotas del contrato.
     *//*
    public function cuotas(): HasMany
    {
        // Asume que existe un modelo App\Models\ContratoCuota
        return $this->hasMany(ContratoCuota::class)->orderBy('numero');
    }
    */

    /*
    /**
     * Relación con los cargos adicionales del contrato.
     *//*
    public function cargos(): HasMany
    {
        // Asume que existe un modelo App\Models\ContratoCargo
        return $this->hasMany(ContratoCargo::class)->orderByDesc('fecha_aplicado');
    }
    */

    /*
    /**
     * Relación con los pagos registrados para el contrato.
     *//*
    public function pagos(): HasMany
    {
        // Asume que existe un modelo App\Models\ContratoPago
        return $this->hasMany(ContratoPago::class)->orderByDesc('fecha');
    }
    */
    // --- FIN: Relaciones comentadas ---


    /**
     * Relación polimórfica con las actuaciones.
     */
    public function actuaciones(): MorphMany
    {
        return $this->morphMany(Actuacion::class, 'actuable')->latest(); // Ordenar por más reciente
    }

    // --- INICIO: AÑADIR RELACIÓN DE REVISIÓN DIARIA ---
    /**
     * Obtiene todas las revisiones diarias para este contrato.
     */
    public function revisionesDiarias(): MorphMany
    {
        return $this->morphMany(RevisionDiaria::class, 'revisable');
    }
    // --- FIN: AÑADIR RELACIÓN DE REVISIÓN DIARIA ---
}