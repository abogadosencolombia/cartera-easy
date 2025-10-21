<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BitacoraCaso extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    // Le decimos explícitamente el nombre de la tabla.
    protected $table = 'bitacoras_caso';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'caso_id',
        'user_id',
        'accion',
        'comentario',
    ];

    /**
     * Una entrada de bitácora pertenece a un Caso.
     */
    public function caso(): BelongsTo
    {
        return $this->belongsTo(Caso::class);
    }

    /**
     * Una entrada de bitácora es creada por un User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}