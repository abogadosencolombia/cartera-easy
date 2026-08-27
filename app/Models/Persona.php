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

    public function scopeSearchSmart($query, ?string $search)
    {
        $words = array_values(array_filter(preg_split('/\s+/', trim((string) $search))));

        foreach ($words as $word) {
            $normalized = self::normalizeSearchTerm($word);
            $cleanNumber = preg_replace('/[^0-9]/', '', $word);

            $query->where(function ($subq) use ($word, $normalized, $cleanNumber) {
                $subq->where('nombre_completo', 'ilike', "%{$word}%")
                    ->orWhere('numero_documento', 'ilike', "%{$word}%")
                    ->orWhereRaw(
                        "TRANSLATE(nombre_completo, 'áéíóúüÁÉÍÓÚÜñÑ', 'aeiouuAEIOUUnN') ILIKE ?",
                        ["%{$normalized}%"]
                    );

                if ($cleanNumber) {
                    $subq->orWhere('numero_documento', 'ilike', "%{$cleanNumber}%");
                }
            });
        }

        return $query;
    }

    private static function normalizeSearchTerm(string $term): string
    {
        return strtr($term, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
        ]);
    }

    public function esRegistroIncompleto(): bool
    {
        $numeroDocumento = strtoupper(trim((string) $this->numero_documento));
        $nombre = str(self::normalizeSearchTerm((string) $this->nombre_completo))->lower()->toString();

        return blank($this->nombre_completo)
            || blank($numeroDocumento)
            || str_starts_with($numeroDocumento, 'TEMP-')
            || str_contains($nombre, 'por identificar')
            || str_contains($nombre, 'persona indeterminada');
    }

    protected $fillable = [
        'nombre_completo', 'tipo_documento', 'numero_documento', 'dv', 'telefono_fijo',
        'celular_1', 'celular_2', 'correo_1', 'correo_2', 'empresa', 'cargo', 'es_demandado',
        'estado_cartera', 'sin_empresa_o_cooperativa',
        'observaciones', 'social_links', 'addresses', 'fecha_expedicion', 'fecha_nacimiento',
        'direccion', 'ciudad', // ✅ Añadidos campos que faltaban
    ];

    protected $casts = [
        'addresses' => 'array',
        'social_links' => 'array',
        'fecha_expedicion' => 'date',
        'fecha_nacimiento' => 'date',
        'es_demandado' => 'boolean',
        'sin_empresa_o_cooperativa' => 'boolean',
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
