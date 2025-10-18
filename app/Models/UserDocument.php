<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserDocument extends Model
{
    use HasFactory; // Es buena práctica añadir esto

    protected $fillable = ['user_id', 'name', 'path'];

    /**
     * Obtiene el usuario al que pertenece el documento.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}