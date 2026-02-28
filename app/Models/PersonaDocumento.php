<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonaDocumento extends Model
{
    protected $fillable = [
        'persona_id',
        'nombre_original',
        'ruta_archivo',
        'mime_type',
        'size',
        'uploaded_by'
    ];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class)->withTrashed();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}