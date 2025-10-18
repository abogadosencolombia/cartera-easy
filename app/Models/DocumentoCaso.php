<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoCaso extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    // Le decimos explícitamente el nombre de la tabla para evitar problemas
    // con la pluralización automática de Laravel.
    protected $table = 'documentos_caso';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'caso_id',
        'tipo_documento',
        'archivo',
        'fecha_carga',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'fecha_carga' => 'date',
    ];

    /**
     * Un DocumentoCaso pertenece a un Caso.
     */
    public function caso(): BelongsTo
    {
        return $this->belongsTo(Caso::class);
    }
}
