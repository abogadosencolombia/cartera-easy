<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaGestionArchivo extends Model
{
    use HasFactory;

    protected $fillable = [
        'nota_gestion_id',
        'nombre_original',
        'ruta_archivo',
        'mime_type',
        'size',
        'uploaded_by'
    ];

    public function notaGestion(): BelongsTo
    {
        return $this->belongsTo(NotaGestion::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
