<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Juzgado extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'juzgados';

    // Usamos $guarded = [] para decirle a Laravel: 
    // "Confía en mí, déjame guardar datos en CUALQUIER columna que exista en la base de datos"
    // Esto evita errores si una columna se llama 'ciudad' en vez de 'municipio'.
    protected $guarded = []; 

    // Opcional: Si quieres asegurar que las fechas se manejen bien
}