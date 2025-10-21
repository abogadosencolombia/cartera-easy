<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ValidacionLegal extends Model
{
    use HasFactory;
    
    // Nombres de los tipos para mostrar en la UI
    public const TIPOS_VALIDACION = [
        'poder_vencido' => 'Poder Vencido',
        'tasa_usura' => 'Tasa de Usura Excedida',
        'sin_pagare' => 'Falta Pagaré', // Nota: El tipo en la BD es sin_pagaré
        'sin_carta_instrucciones' => 'Falta Carta de Instrucciones',
        'sin_certificacion_saldo' => 'Falta Certificación de Saldo',
        'tipo_proceso_vs_garantia' => 'Proceso vs. Garantía Inconsistente',
        'plazo_excedido_sin_demanda' => 'Plazo para Demandar Excedido',
        'documento_faltante_para_radicar' => 'Documentos Faltantes para Radicar',
    ];

    protected $fillable = [
        'caso_id',
        'requisito_id', // <-- AÑADIDO: Campo clave para la relación
        'tipo',
        'estado',
        'observacion',
        'ultima_revision',
        'nivel_riesgo',
        'accion_correctiva',
        'fecha_cumplimiento',
    ];

    protected $casts = [
        'ultima_revision' => 'datetime',
        'fecha_cumplimiento' => 'datetime',
    ];

    protected $table = 'validaciones_legales';
    
    /**
     * Obtiene el caso al que pertenece esta validación.
     */
    public function caso(): BelongsTo
    {
        return $this->belongsTo(Caso::class);
    }
    
    /**
     * ===== ¡LA RELACIÓN QUE FALTABA! =====
     * Obtiene el requisito (la regla) que originó esta validación.
     * Esto le permite al sistema saber qué documento se está evaluando.
     */
    public function requisito(): BelongsTo
    {
        return $this->belongsTo(RequisitoDocumento::class, 'requisito_id');
    }

    /**
     * Obtiene el historial de cambios para esta validación.
     */
    public function historial(): HasMany
    {
        return $this->hasMany(HistorialValidacionLegal::class)->orderBy('created_at', 'desc');
    }
}
