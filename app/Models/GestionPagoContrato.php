<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GestionPagoContrato extends Model
{
    use HasFactory;

    /**
     * 1. Apuntar a la tabla correcta
     */
    protected $table = 'contrato_pagos';

    /**
     * 2. Definir las columnas REALES (de tu migración)
     */
    protected $fillable = [
        'contrato_id',
        'cuota_id',
        'valor',
        'fecha', // <-- ¡CORRECCIÓN DEFINITIVA!
        'metodo',
        'nota',
        'comprobante',
    ];

    /**
     * 3. Definir los casts para las columnas REALES
     */
    protected $casts = [
        'fecha' => 'date', // <-- ¡CORRECCIÓN DEFINITIVA!
        'valor' => 'decimal:2',
    ];

    /**
     * 4. Relación a Contrato (¡Esto es vital!)
     */
    public function contrato()
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }
}