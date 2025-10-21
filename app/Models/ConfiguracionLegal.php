<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfiguracionLegal extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'configuraciones_legales'; // <-- AGREGA ESTA LÍNEA

    protected $fillable = [
        'cooperativa_id',
        'tasa_maxima_usura',
        'dias_maximo_para_demandar',
        'exige_pagare',
        'exige_carta_instrucciones',
        'exige_certificacion_saldo',
    ];

    public function cooperativa(): BelongsTo
    {
        return $this->belongsTo(Cooperativa::class);
    }
}