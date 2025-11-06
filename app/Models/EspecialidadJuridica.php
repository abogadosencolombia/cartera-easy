<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany; // <-- AÑADIDO

class EspecialidadJuridica extends Model
{
    use HasFactory;

    protected $table = 'especialidades_juridicas';

    protected $fillable = ['nombre','descripcion'];

    public function casos(): HasMany // <-- CORREGIDO (añadido tipo HasMany)
    {
        return $this->hasMany(Caso::class, 'especialidad_id');
    }

    /**
     * =================================================================
     * NUEVA RELACIÓN AÑADIDA
     * Una especialidad tiene muchos tipos de proceso (ej. CIVIL tiene EJECUTIVO)
     * =================================================================
     */
    public function tiposProceso(): HasMany
    {
        return $this->hasMany(TipoProceso::class, 'especialidad_juridica_id');
    }
}