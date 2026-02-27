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

    protected $fillable = [
        'nombre_completo', 'tipo_documento', 'numero_documento', 'telefono_fijo',
        'celular_1', 'celular_2', 'correo_1', 'correo_2', 'empresa', 'cargo', 'es_demandado',
        'observaciones', 'social_links', 'addresses', 'fecha_expedicion', 'fecha_nacimiento',
    ];

    // ... (Mantener tus Accessors y Mutators existentes) ...

    protected function fechaExpedicion(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->format('Y-m-d') : null,
            set: fn ($value) => $value ?: null
        );
    }

    protected function fechaNacimiento(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->format('Y-m-d') : null,
            set: fn ($value) => $value ?: null
        );
    }

    protected function addresses(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? json_decode($value, true) : [],
            set: fn ($value) => is_array($value) ? json_encode($value) : null
        );
    }

    protected function socialLinks(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? json_decode($value, true) : [],
            set: fn ($value) => is_array($value) ? json_encode($value) : null
        );
    }

    // --- RELACIONES ---

    // ✅ NUEVA RELACIÓN PARA DOCUMENTOS
    public function documentos(): HasMany
    {
        return $this->hasMany(PersonaDocumento::class, 'persona_id')->latest();
    }

    public function casosComoDeudor(): HasMany
    {
        return $this->hasMany(Caso::class, 'deudor_id');
    }

    public function casos(): HasMany
    {
        return $this->casosComoDeudor();
    }

    public function procesos(): BelongsToMany
    {
        return $this->belongsToMany(Proceso::class, 'proceso_radicado_personas', 'persona_id', 'proceso_radicado_id')
            ->wherePivot('tipo', 'DEMANDADO')
            ->withPivot('tipo')
            ->withTimestamps();
    }

    public function cooperativas(): BelongsToMany
    {
        return $this->belongsToMany(Cooperativa::class, 'cooperativa_persona')
            ->withPivot('cargo', 'status')
            ->withTimestamps();
    }

    public function abogados(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'persona_user', 'persona_id', 'abogado_id')
            ->withTimestamps();
    }
}