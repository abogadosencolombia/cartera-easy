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
        'nombre_completo', 'tipo_documento', 'numero_documento', 'dv', 'telefono_fijo',
        'celular_1', 'celular_2', 'correo_1', 'correo_2', 'empresa', 'cargo', 'es_demandado',
        'observaciones', 'social_links', 'addresses', 'fecha_expedicion', 'fecha_nacimiento',
        'direccion', 'ciudad', // ✅ Añadidos campos que faltaban
    ];

    protected $casts = [
        'addresses' => 'array',
        'social_links' => 'array',
        'fecha_expedicion' => 'date',
        'fecha_nacimiento' => 'date',
        'es_demandado' => 'boolean',
    ];

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

    public function casosComoCodeudor(): BelongsToMany
    {
        // Nota: Asumimos que los codeudores están en la tabla 'codeudores' 
        // pero el usuario puede querer que si una Persona existe con el mismo documento
        // aparezcan sus casos. Sin embargo, la BD actual separa Persona de Codeudor.
        // Por ahora, vinculamos a través de la tabla pivote si existe relación.
        // Si no hay relación directa en la BD entre Persona y Caso como codeudor,
        // lo ideal sería buscar por número de documento.
        return $this->belongsToMany(Caso::class, 'caso_codeudor', 'codeudor_id', 'caso_id');
    }

    public function casos(): HasMany
    {
        return $this->casosComoDeudor();
    }

    public function procesosComoDemandado(): BelongsToMany
    {
        return $this->belongsToMany(ProcesoRadicado::class, 'proceso_radicado_personas', 'persona_id', 'proceso_radicado_id')
            ->wherePivot('tipo', 'DEMANDADO')
            ->withPivot('tipo')
            ->withTimestamps();
    }

    public function procesosComoDemandante(): BelongsToMany
    {
        return $this->belongsToMany(ProcesoRadicado::class, 'proceso_radicado_personas', 'persona_id', 'proceso_radicado_id')
            ->wherePivot('tipo', 'DEMANDANTE')
            ->withPivot('tipo')
            ->withTimestamps();
    }

    public function procesos(): BelongsToMany
    {
        return $this->belongsToMany(ProcesoRadicado::class, 'proceso_radicado_personas', 'persona_id', 'proceso_radicado_id')
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