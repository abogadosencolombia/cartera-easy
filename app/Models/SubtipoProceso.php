<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubtipoProceso extends Model
{
    use HasFactory;

    protected $table = 'subtipos_proceso';
    protected $fillable = ['tipo_proceso_id','nombre','descripcion'];

    public function tipo()
    {
        return $this->belongsTo(TipoProceso::class, 'tipo_proceso_id');
    }
}
