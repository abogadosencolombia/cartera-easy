<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Persona extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nombre_completo',
        'tipo_documento',
        'numero_documento',
        'telefono_fijo',
        'celular_1',
        'celular_2',
        'correo_1',
        'correo_2',
        'empresa', // Este SÍ está en tu formulario
        'cargo',   // Este SÍ está en tu formulario
        'observaciones',
        'social_links', // Este es tu array de redes sociales
        'addresses',    // Este es tu array de direcciones
        'fecha_expedicion', // <-- ¡Este es el campo que da el error!
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'social_links' => 'array',
        'addresses' => 'array',
        'fecha_expedicion' => 'date', // Aseguramos el cast a fecha
    ];
    
    /**
     * Formatea la fecha de expedición.
     */
    protected function fechaExpedicion(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->format('Y-m-d') : null,
            set: fn ($value) => $value ?: null
        );
    }

    /**
     * Relación: Casos donde la persona es el deudor.
     */
    public function casosComoDeudor(): HasMany
    {
        return $this->hasMany(Caso::class, 'deudor_id');
    }

    /**
     * Relación: Cooperativas a las que pertenece la persona.
     */
    public function cooperativas(): BelongsToMany
    {
        return $this->belongsToMany(Cooperativa::class, 'cooperativa_persona')
                    ->withPivot('cargo', 'status') 
                    ->withTimestamps();
    }
}

