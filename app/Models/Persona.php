<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class Persona extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nombre_completo',
        'tipo_documento',
        'numero_documento',
        'telefono_fijo',
        'celular_1',
        'celular_2',
        'correo_1',
        'correo_2',
        'empresa',
        'cargo',
        'observaciones',
        'social_links',
        'addresses',
        'fecha_expedicion',
        'fecha_nacimiento',
    ];

    // ... (Accessors se mantienen igual, no borrar) ...

    protected function fechaExpedicion(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (empty($value)) return null;
                try {
                    return Carbon::parse($value)->format('Y-m-d');
                } catch (\Exception $e) {
                    return null;
                }
            },
            set: fn ($value) => $value ?: null
        );
    }

    protected function fechaNacimiento(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (empty($value)) return null;
                try {
                    return Carbon::parse($value)->format('Y-m-d');
                } catch (\Exception $e) {
                    return null;
                }
            },
            set: fn ($value) => $value ?: null
        );
    }

    protected function addresses(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (empty($value)) return [];
                try {
                    $data = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                    return is_array($data) ? $data : [];
                } catch (\JsonException $e) {
                    return [];
                }
            },
            set: fn ($value) => is_array($value) ? json_encode($value) : null
        );
    }

    protected function socialLinks(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (empty($value)) return [];
                try {
                    $data = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                    return is_array($data) ? $data : [];
                } catch (\JsonException $e) {
                    return [];
                }
            },
            set: fn ($value) => is_array($value) ? json_encode($value) : null
        );
    }

    // --- RELACIONES ---

    public function casosComoDeudor(): HasMany
    {
        return $this->hasMany(Caso::class, 'deudor_id');
    }

    public function casos(): HasMany
    {
        return $this->casosComoDeudor();
    }

    /**
     * ✅ CORRECCIÓN FINAL: Relación Muchos a Muchos.
     * Usamos la tabla pivot 'proceso_radicado_personas' que aparece en tu BD.
     * Filtramos por 'tipo' = 'DEMANDADO' para traer solo los procesos donde esta persona es demandada.
     */
    public function procesos(): BelongsToMany
    {
        return $this->belongsToMany(
                Proceso::class, 
                'proceso_radicado_personas', // Tabla intermedia (Pivot)
                'persona_id',                // FK de Persona en la pivot
                'proceso_radicado_id'        // FK de Proceso en la pivot
            )
            ->wherePivot('tipo', 'DEMANDADO') // Filtramos solo donde actúa como Demandado
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