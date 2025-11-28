<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContratoCuota extends Model
{
    use HasFactory;

    protected $table = 'contrato_cuotas';

    protected $fillable = [
        'contrato_id',
        'numero',
        'fecha_vencimiento',
        'valor',
        'estado',
        'fecha_pago',
    ];

    public function contrato()
    {
        return $this->belongsTo(Contrato::class);
    }
}