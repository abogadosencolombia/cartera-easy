<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder; // <-- Añadido para Query Scopes

class Actuacion extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'actuaciones';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'actuable_type',
        'actuable_id',
        'user_id', // Quién creó
        'nota',
        'fecha_actuacion', // <-- Añadido
        'verified_by_user_id', // Quién verificó
        'verified_at', // Cuándo verificó
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'verified_at' => 'datetime',
        'created_at' => 'datetime', // Fecha en que se registró en el sistema
        'updated_at' => 'datetime',
        'fecha_actuacion' => 'date', // <-- Añadido: Fecha real de la actuación
    ];

    /**
     * Get the parent actuable model (Caso, ProcesoRadicado, Contrato).
     */
    public function actuable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who created the actuation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the user who verified the actuation.
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    /**
     * Scope a query to only include pending actuations.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    // --- Corregido: Añadir tipo Builder ---
    public function scopePendiente(Builder $query): Builder
    {
        return $query->whereNull('verified_at');
    }

    /**
     * Scope a query to only include verified actuations.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    // --- Corregido: Añadir tipo Builder ---
    public function scopeVerificada(Builder $query): Builder
    {
        return $query->whereNotNull('verified_at');
    }
}

