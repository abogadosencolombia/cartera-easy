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
use Illuminate\Support\Facades\Log;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Illuminate\Database\Eloquent\SoftDeletes; // <--- 1. IMPORTANTE: Importar SoftDeletes

class User extends Authenticatable
{
    // 2. IMPORTANTE: Agregar SoftDeletes al use
    use HasFactory, Notifiable, HasPushSubscriptions, SoftDeletes;

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
        'addresses',
        'tour_seen',
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
     */
    protected $appends = ['cooperativas_display'];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'estado_activo' => 'boolean',
            'tour_seen' => 'boolean',
            'preferencias_notificacion' => 'array',
        ];
    }

    // --- RELACIONES ---

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
        return $this->belongsTo(Persona::class)->withTrashed();
    }

    public function documentosGenerados(): HasMany
    {
        return $this->hasMany(DocumentoGenerado::class);
    }

    public function casos(): HasMany
    {
        return $this->hasMany(Caso::class);
    }

    public function procesos(): HasMany
    {
        return $this->hasMany(ProcesoRadicado::class, 'abogado_id');
    }

    public function pagos(): HasManyThrough
    {
        return $this->hasManyThrough(PagoCaso::class, Caso::class);
    }
    
    public function notificaciones(): HasMany
    {
        return $this->hasMany(NotificacionCaso::class);
    }

    public function personasAsignadas(): BelongsToMany
    {
        return $this->belongsToMany(Persona::class, 'persona_user', 'abogado_id', 'persona_id')
                    ->withTrashed()
                    ->withTimestamps();
    }

    // --- ACCESSORS ---

    protected function cooperativasDisplay(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->cooperativas->isEmpty()) {
                    return 'Ninguna';
                }
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

    protected function addresses(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (empty($value)) {
                    return [];
                }

                try {
                    $data = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                    return is_array($data) ? $data : [];
                } catch (\JsonException $e) {
                    Log::warning("JSON inválido en 'addresses' para User ID: {$this->id}. Valor: {$value}");
                    return [];
                }
            },
            set: fn ($value) => is_array($value) ? json_encode($value) : null
        );
    }
    
    // ===== MÓDULO DE TAREAS =====

    public function isAdmin(): bool
    {
        return $this->tipo_usuario === 'admin';
    }

    public function isGestor(): bool
    {
        return $this->tipo_usuario === 'gestor';
    }

    public function isAbogado(): bool
    {
        return $this->tipo_usuario === 'abogado';
    }

    public function isCliente(): bool
    {
        return $this->tipo_usuario === 'cliente';
    }

    public function hasRole(string|array $roles): bool
    {
        if (is_array($roles)) {
            return in_array($this->tipo_usuario, $roles);
        }
        return $this->tipo_usuario === $roles;
    }

    public function tareasAsignadas(): HasMany
    {
        return $this->hasMany(Tarea::class, 'user_id');
    }

    public function tareasCreadas(): HasMany
    {
        return $this->hasMany(Tarea::class, 'admin_id');
    }
}