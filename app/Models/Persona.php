<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany; // <--- AÑADIDO
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;

class Persona extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre_completo',
        'tipo_documento',
        'numero_documento',
        'fecha_expedicion',
        'telefono_fijo',
        'celular_1',
        'celular_2',
        'correo_1',
        'correo_2',
        // 'empresa', // <-- COMENTADO: Este campo ya no es necesario aquí.
        // 'cargo',   // <-- COMENTADO: Se gestionará en la tabla pivote.
        'observaciones',
        'social_links',
        'addresses',
    ];

    protected $casts = [
        'social_links' => 'array',
        'addresses' => 'array',
    ];
    
    protected function fechaExpedicion(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->format('Y-m-d') : null,
            set: fn ($value) => $value ?: null
        );
    }

    public function casosComoDeudor(): HasMany
    {
        return $this->hasMany(Caso::class, 'deudor_id');
    }

    // --- CÓDIGO AÑADIDO ---
    /**
     * Las cooperativas a las que pertenece la persona.
     */
    public function cooperativas(): BelongsToMany
    {
        return $this->belongsToMany(Cooperativa::class, 'cooperativa_persona')
                    ->withPivot('cargo', 'status') // Permite acceder a los campos de la tabla pivote
                    ->withTimestamps();
    }
}