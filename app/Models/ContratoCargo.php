<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContratoCargo extends Model
{
    use HasFactory;

    protected $table = 'contrato_cargos';

    protected $fillable = [
        'contrato_id',
        'tipo',
        'monto',
        'estado',
        'descripcion',
        'comprobante',
        'fecha_aplicado',
        'fecha_inicio_intereses',
        'pago_id',
        'fecha_pago',
        'intereses_mora_acumulados' // Importante si vas a calcular mora
    ];

    public function contrato()
    {
        return $this->belongsTo(Contrato::class);
    }
}