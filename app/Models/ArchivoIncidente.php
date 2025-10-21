<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArchivoIncidente extends Model
{
    use HasFactory;

    protected $table = 'archivos_incidente';

    protected $fillable = [
        'incidente_id',
        'subido_por_id',
        'nombre_original',
        'ruta_archivo',
        'tipo_archivo',
    ];

    // --- RELACIONES ---

    // El archivo pertenece a un incidente.
    public function incidente()
    {
        return $this->belongsTo(IncidenteJuridico::class, 'incidente_id');
    }

    // El archivo fue subido por un usuario.
    public function subidoPor()
    {
        return $this->belongsTo(User::class, 'subido_por_id');
    }
}