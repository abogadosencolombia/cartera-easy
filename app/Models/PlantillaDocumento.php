<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany; // <-- Importación añadida

class PlantillaDocumento extends Model
{
    use HasFactory;

    protected $table = 'plantillas_documento';

    protected $fillable = [
        'cooperativa_id',
        'nombre',
        'tipo',
        'archivo',
        'version',
        'aplica_a',
        'activa',
    ];

    protected $casts = [
        'aplica_a' => 'array',
        'activa' => 'boolean',
    ];

    public function cooperativa()
    {
        return $this->belongsTo(Cooperativa::class);
    }

    /**
     * --- RELACIÓN AÑADIDA PARA LA VICTORIA FINAL ---
     * Una plantilla puede ser usada para generar muchos documentos.
     */
    public function documentosGenerados(): HasMany
    {
        return $this->hasMany(DocumentoGenerado::class);
    }
}
