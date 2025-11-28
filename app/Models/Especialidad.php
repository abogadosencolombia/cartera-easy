<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Especialidad extends Model
{
    use HasFactory;

    // IMPORTANTE: Definir la tabla explícitamente
    protected $table = 'especialidades';

    protected $fillable = ['nombre', 'descripcion'];

    /**
     * Relación muchos a muchos con usuarios (abogados/gestores).
     */
    public function users(): BelongsToMany
    {
        // Definimos explícitamente la tabla pivote y las llaves foráneas para evitar errores
        return $this->belongsToMany(User::class, 'especialidad_user', 'especialidad_id', 'user_id')
                    ->withTimestamps();
    }
}