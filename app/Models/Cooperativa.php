<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
// No necesitas importar Persona::class si usas su notación completa, pero es buena práctica.

class Cooperativa extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'NIT',
        'tipo_vigilancia',
        'fecha_constitucion',
        'numero_matricula_mercantil',
        'tipo_persona',
        'representante_legal_nombre',
        'representante_legal_cedula',
        'contacto_nombre',
        'contacto_telefono',
        'contacto_correo',
        'correo_notificaciones_judiciales',
        'usa_libranza',
        'requiere_carta_instrucciones',
        'tipo_garantia_frecuente',
        'tasa_maxima_moratoria',
        'ciudad_principal_operacion',
        'estado_activo',
        // 'configuraciones_adicionales' no es fillable, se maneja por casts
    ];

    protected $casts = [
        'configuraciones_adicionales' => 'array',
        'usa_libranza' => 'boolean',
        'requiere_carta_instrucciones' => 'boolean',
        'estado_activo' => 'boolean',
        'fecha_constitucion' => 'date', // Recomendado añadir este cast
    ];

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoLegal::class);
    }

    public function getConfiguracion($clave, $default = null)
    {
        return $this->configuraciones_adicionales[$clave] ?? $default;
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'cooperativa_user');
    }

    public function configuracionLegal(): HasOne
    {
        return $this->hasOne(ConfiguracionLegal::class);
    }

    // --- CÓDIGO AÑADIDO ---
    /**
     * Las personas asociadas a la cooperativa.
     */
    public function personas(): BelongsToMany
    {
        return $this->belongsToMany(Persona::class, 'cooperativa_persona')
                    ->withPivot('cargo', 'status') // Permite acceder a los campos de la tabla pivote
                    ->withTimestamps();
    }
}