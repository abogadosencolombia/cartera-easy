<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\AuditoriaEvento;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Caso extends Model
{
    use HasFactory;

    protected $fillable = [
        'cooperativa_id', 'user_id', 'deudor_id', 'codeudor1_id', 'codeudor2_id',
        'referencia_credito', 'tipo_proceso', 'estado_proceso', 'tipo_garantia_asociada',
        'fecha_apertura', 'fecha_vencimiento', 'monto_total', 'tasa_interes_corriente',
        'tasa_moratoria', 'origen_documental', 'bloqueado', 'motivo_bloqueo',
        'etapa_actual', 'medio_contacto', 'ultima_actividad', 'notas_legales',
        'clonado_de_id',
        'subtipo_proceso',
        'etapa_procesal',
        'juzgado_id', 'especialidad_id',
        'estado', // <-- AÑADIDO: Campo clave para la lógica del listener
    ];

    protected $casts = [
        'fecha_apertura' => 'date',
        'fecha_vencimiento' => 'date',
        'monto_total' => 'decimal:2',
        'tasa_interes_corriente' => 'decimal:2',
        'tasa_moratoria' => 'decimal:2',
        'bloqueado' => 'boolean',
        'ultima_actividad' => 'datetime',
    ];

    protected $appends = ['semaforo', 'dias_en_mora'];

    // --- TUS ACCESSORS (diasEnMora y semaforo) VAN AQUÍ ---
    // (No los modifico, están bien como están)
    protected function diasEnMora(): Attribute { /* ...tu código... */ }
    protected function semaforo(): Attribute { /* ...tu código... */ }


    // --- RELACIONES ---
    public function cooperativa(): BelongsTo { return $this->belongsTo(Cooperativa::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function deudor(): BelongsTo { return $this->belongsTo(Persona::class, 'deudor_id'); } // <-- ¡Esta es la importante!
    public function codeudor1(): BelongsTo { return $this->belongsTo(Persona::class, 'codeudor1_id');}
    public function codeudor2(): BelongsTo { return $this->belongsTo(Persona::class, 'codeudor2_id');}
    public function documentos(): HasMany { return $this->hasMany(DocumentoCaso::class); }
    public function bitacoras(): HasMany { return $this->hasMany(BitacoraCaso::class)->orderBy('created_at', 'desc'); }
    public function pagos(): HasMany { return $this->hasMany(PagoCaso::class); }
    public function alertas(): HasMany { return $this->hasMany(AlertaCaso::class); }
    public function historialMora(): HasMany { return $this->hasMany(HistorialMora::class); }
    public function documentosGenerados(): HasMany { return $this->hasMany(DocumentoGenerado::class)->orderBy('created_at', 'desc'); }
    public function notificaciones(): HasMany { return $this->hasMany(NotificacionCaso::class); }
    public function validacionesLegales(): HasMany { return $this->hasMany(ValidacionLegal::class); }
    public function configuracionLegal(): HasOne { return $this->hasOne(ConfiguracionLegal::class); }

    public function auditoria(): MorphMany
    {
        return $this->morphMany(AuditoriaEvento::class, 'auditable')->latest();
    }

    public function juzgado(): BelongsTo 
    { 
        return $this->belongsTo(Juzgado::class, 'juzgado_id'); 
    }

    public function especialidad()
    {
        return $this->belongsTo(\App\Models\EspecialidadJuridica::class, 'especialidad_id');
    }
}