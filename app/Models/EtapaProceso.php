<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EtapaProceso extends Model
{
    protected $table = 'etapas_proceso';
    protected $fillable = ['tipo_proceso_id', 'nombre', 'orden'];
    public $timestamps = false;

    public function tipo()
    {
        return $this->belongsTo(TipoProceso::class, 'tipo_proceso_id');
    }
}
