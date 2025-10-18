<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Especialidad extends Model
{
    use HasFactory;

    protected $table = 'especialidades';

    protected $fillable = ['nombre', 'descripcion'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'especialidad_user');
    }
}
