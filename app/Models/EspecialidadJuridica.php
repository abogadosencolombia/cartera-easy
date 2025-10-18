<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EspecialidadJuridica extends Model
{
    use HasFactory;

    protected $table = 'especialidades_juridicas';

    protected $fillable = ['nombre','descripcion'];

    public function casos() {
        return $this->hasMany(Caso::class, 'especialidad_id');
    }
}
