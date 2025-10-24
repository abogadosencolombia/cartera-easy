<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasPushSubscriptions;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'tipo_usuario',
        'estado_activo',
        'persona_id',
        'preferencias_notificacion',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The accessors to append to the model's array form.
     * ESTA LÍNEA LE DICE A LARAVEL QUE SIEMPRE INCLUYA NUESTRO ATRIBUTO MÁGICO
     * @var array
     */
    protected $appends = ['cooperativas_display']; // <-- 2. LÍNEA AÑADIDA

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'estado_activo' => 'boolean',
            'preferencias_notificacion' => 'array',
        ];
    }

    // --- RELACIONES (Tus relaciones existentes se mantienen intactas) ---

    public function cooperativas(): BelongsToMany
    {
        return $this->belongsToMany(Cooperativa::class, 'cooperativa_user');
    }

    public function especialidades(): BelongsToMany
    {
        return $this->belongsToMany(Especialidad::class, 'especialidad_user');
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function documentosGenerados(): HasMany
    {
        return $this->hasMany(DocumentoGenerado::class);
    }

    public function casos(): HasMany
    {
        return $this->hasMany(Caso::class);
    }

    public function pagos(): HasManyThrough
    {
        return $this->hasManyThrough(PagoCaso::class, Caso::class);
    }
    
    public function notificaciones(): HasMany
    {
        return $this->hasMany(NotificacionCaso::class);
    }

    /**
     * Relación: Personas asignadas a este abogado (User).
     */
    public function personasAsignadas(): BelongsToMany
    {
        // Esta es la relación inversa de la que definimos en Persona.php
        return $this->belongsToMany(Persona::class, 'persona_user', 'abogado_id', 'persona_id')
                    ->withTimestamps();
    }

    // --- ACCESSORS (ATRIBUTOS MÁGICOS) ---

    /**
     * Crea un atributo virtual que muestra los nombres de las cooperativas como un string.
     * ESTA ES LA FUNCIÓN NUEVA QUE SOLUCIONA EL PROBLEMA.
     */
    protected function cooperativasDisplay(): Attribute
    {
        return Attribute::make(
            get: function () {
                // Si el usuario no tiene cooperativas, devuelve 'Ninguna'.
                if ($this->cooperativas->isEmpty()) {
                    return 'Ninguna';
                }
                // Si tiene, une todos los nombres con una coma.
                return $this->cooperativas->pluck('nombre')->join(', ');
            }
        );
    }

    protected function especialidadesDisplay(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->especialidades->isEmpty()) {
                    return 'Ninguna';
                }
                return $this->especialidades->pluck('nombre')->join(', ');
            }
        );
    }
  
  public function documents()
  {
      return $this->hasMany(UserDocument::class);
  }
  
}
