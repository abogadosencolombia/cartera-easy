<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContratoPago extends Model
{
    use HasFactory;

    protected $table = 'contrato_pagos';

    protected $fillable = [
        'contrato_id',
        'cuota_id',
        'cargo_id',
        'valor',
        'fecha',
        'metodo',
        'nota',
        'comprobante',
    ];

    public function contrato()
    {
        return $this->belongsTo(Contrato::class);
    }
}