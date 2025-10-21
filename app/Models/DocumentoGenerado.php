<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoGenerado extends Model
{
    use HasFactory;

    protected $table = 'documentos_generados';

    // Lista de campos actualizada para incluir las nuevas rutas
    protected $fillable = [
        'caso_id',
        'plantilla_documento_id',
        'user_id',
        'nombre_base', // Actualizado
        'ruta_archivo_docx', // Actualizado
        'ruta_archivo_pdf',  // Nuevo
        'version_plantilla',
        'observaciones',
        'es_confidencial',
        'metadatos',
    ];

    protected $casts = [
        'es_confidencial' => 'boolean',
        'metadatos' => 'array',
    ];

    public function caso(): BelongsTo
    {
        return $this->belongsTo(Caso::class);
    }

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(PlantillaDocumento::class, 'plantilla_documento_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
