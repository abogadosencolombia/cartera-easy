<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
// --- AÑADIR ESTA IMPORTACIÓN ---
use Illuminate\Support\Facades\Log;

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
        'fecha_expedicion', 
    ];

    /**
    * The attributes that should be cast.
    *
    * @var array<string, string>
    */
    protected $casts = [
        // --- INICIO DE LA CORRECCIÓN ---
        // Se quitan los casts de array para que los accessors
        // se encarguen de la decodificación de forma segura.
        // 'social_links' => 'array',
        // 'addresses' => 'array',
        // --- FIN DE LA CORRECCIÓN ---
        
        // 'fecha_expedicion' => 'date', // <-- Esto ya estaba bien comentado
    ];
    
    /**
    * Formatea la fecha de expedición de forma segura.
    */
    protected function fechaExpedicion(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (empty($value)) {
                    return null;
                }
                
                try {
                    // Intentamos parsear la fecha
                    return Carbon::parse($value)->format('Y-m-d');
                } catch (\Exception $e) {
                    // Si falla (ej. "0000-00-00" o texto inválido),
                    // devolvemos null en lugar de causar un error 500.
                    return null;
                }
            },
            
            set: fn ($value) => $value ?: null
        );
    }

    // --- INICIO: NUEVOS ACCESSORS SEGUROS PARA JSON ---

    /**
     * Decodifica de forma segura el campo 'addresses'.
     */
    protected function addresses(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (empty($value)) {
                    return []; // Devolver un array vacío por defecto
                }

                try {
                    // Intentar decodificar el JSON
                    $data = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                    // Asegurarse de que es un array
                    return is_array($data) ? $data : [];
                } catch (\JsonException $e) {
                    // Si el JSON es inválido, registrar el error y devolver array vacío
                    Log::warning("JSON inválido en 'addresses' para Persona ID: {$this->id}. Valor: {$value}");
                    return [];
                }
            },
            // El 'set' codifica el array a JSON (para cuando guardas)
            set: fn ($value) => is_array($value) ? json_encode($value) : null
        );
    }

    /**
     * Decodifica de forma segura el campo 'social_links'.
     */
    protected function socialLinks(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (empty($value)) {
                    return []; // Devolver un array vacío por defecto
                }

                try {
                    // Intentar decodificar el JSON
                    $data = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                    // Asegurarse de que es un array
                    return is_array($data) ? $data : [];
                } catch (\JsonException $e) {
                    // Si el JSON es inválido, registrar el error y devolver array vacío
                    Log::warning("JSON inválido en 'social_links' para Persona ID: {$this->id}. Valor: {$value}");
                    return [];
                }
            },
            // El 'set' codifica el array a JSON (para cuando guardas)
            set: fn ($value) => is_array($value) ? json_encode($value) : null
        );
    }

    // --- FIN: NUEVOS ACCESSORS SEGUROS PARA JSON ---


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

    /**
    * Relación: Abogados (Users) asignados a esta persona.
    */
    public function abogados(): BelongsToMany
    {
        // El nombre de la tabla pivote será 'persona_user'
        // 'persona_id' es la llave de este modelo (Persona)
        // 'abogado_id' será la llave del modelo que unimos (User)
        return $this->belongsToMany(User::class, 'persona_user', 'persona_id', 'abogado_id')
                    ->withTimestamps();
    }
}

