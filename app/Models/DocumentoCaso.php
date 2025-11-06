<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage; // <-- AÑADIDO para el Accesor de URL

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
    // --- INICIO: CORRECCIÓN (IMPLEMENTAR POLIMORFISMO) ---
    // Añadimos 'persona_id' al fillable.
    // Asumimos que tu tabla 'documentos_caso' tiene AMBAS columnas:
    // 1. 'persona_id' (nullable, FK a 'personas')
    // 2. 'codeudor_id' (nullable, FK a 'codeudores')
    protected $fillable = [
        'caso_id',
        'tipo_documento',
        'archivo',
        'fecha_carga',
        'persona_id',   // <-- AÑADIDO
        'codeudor_id',
        'nota',
        // 'user_id', // <-- CORRECCIÓN: Eliminado, no existe en la DB
    ];
    // --- FIN: CORRECCIÓN ---

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'fecha_carga' => 'date',
    ];

    /**
     * Los atributos que deben agregarse a las serializaciones del modelo.
     *
     * @var array
     */
    // --- INICIO: CORRECCIÓN ---
    // Eliminado 'url' ya que dependía de 'ruta_archivo' que no existe.
    // protected $appends = ['url'];
    // --- FIN: CORRECCIÓN ---
    /*
    public function getUrlAttribute(): ?string // <-- AÑADIDO
    {
        if ($this->ruta_archivo) {
            // Asumiendo que usas el disco 'public'
            return Storage::disk('public')->url($this->ruta_archivo);
        }
        return null;
    }
    */

    /**
     * Un DocumentoCaso pertenece a un Caso.
     */
    public function caso(): BelongsTo
    {
        return $this->belongsTo(Caso::class);
    }

    /**
     * Un DocumentoCaso es subido por un Usuario.
     */
    // --- INICIO: CORRECCIÓN ---
    // Eliminamos la relación 'user' porque la columna 'user_id' no existe en la BD.
    /*
    public function user(): BelongsTo // <-- AÑADIDO
    {
        return $this->belongsTo(User::class);
    }
    */
    // --- FIN: CORRECCIÓN ---

    // --- INICIO: CORRECCIÓN (RELACIONES POLIMÓRFICAS) ---

    /**
     * Un DocumentoCaso puede pertenecer a una Persona (Deudor).
     */
    public function persona(): BelongsTo
    {
        // Apunta a la tabla 'personas' a través de la FK 'persona_id'
        return $this->belongsTo(Persona::class, 'persona_id');
    }

    /**
     * Un DocumentoCaso puede pertenecer a un Codeudor.
     */
    public function codeudor(): BelongsTo
    {
        // Apunta a la tabla 'codeudores' a través de la FK 'codeudor_id'
        return $this->belongsTo(Codeudor::class, 'codeudor_id');
    }
    // --- FIN: CORRECCIÓN ---
}

