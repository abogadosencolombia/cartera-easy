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
use App\Models\RevisionDiaria;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Codeudor;
use App\Models\Tarea;
use App\Models\Contrato;
use Illuminate\Database\Eloquent\SoftDeletes;

class Caso extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'cooperativa_id', 'user_id', 'deudor_id',
        'referencia_credito', 'radicado',
        'tipo_proceso', 'estado_proceso', 'tipo_garantia_asociada',
        'fecha_apertura', 'fecha_vencimiento',
        'monto_total',
        'monto_deuda_actual',
        'monto_total_pagado',
        'tasa_interes_corriente',
        'fecha_inicio_credito', 
        'origen_documental', 'bloqueado', 'motivo_bloqueo',
        'etapa_actual', 'medio_contacto', 'link_drive', 'link_expediente', 'ultima_actividad', 'notas_legales',
        'clonado_de_id',
        'subtipo_proceso',
        'subproceso',
        'etapa_procesal',
        'juzgado_id', 'especialidad_id',
        'estado',
        'nota_cierre', // <--- ¡AQUÍ ESTABA EL PROBLEMA! Faltaba esta autorización.
        'clonado_de_id', 'is_pinned', 'checklist_seguimiento'
        ];

        protected $casts = [
        'fecha_apertura' => 'date',
        'fecha_vencimiento' => 'date',
        'monto_total' => 'decimal:2',
        'monto_deuda_actual' => 'decimal:2',
        'monto_total_pagado' => 'decimal:2',
        'tasa_interes_corriente' => 'decimal:2',
        'fecha_inicio_credito' => 'date',
        'bloqueado' => 'boolean',
        'ultima_actvidad' => 'datetime',
        'is_pinned' => 'boolean',
        'checklist_seguimiento' => 'array',
        ];

    /**
     * Mutator para normalizar el radicado.
     */
    protected function radicado(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            set: function ($value) {
                if (empty($value)) return null;
                $clean = preg_replace('/[^0-9]/', '', $value);
                return (strlen($clean) === 23) ? $clean : trim($value);
            }
        );
    }
    

    protected $appends = ['semaforo', 'dias_en_mora'];

    protected function diasEnMora(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                if (empty($attributes['fecha_vencimiento'])) {
                    return 0;
                }
                $fechaVencimiento = Carbon::parse($attributes['fecha_vencimiento']);
                $hoy = Carbon::now();
                if ($fechaVencimiento->isFuture()) {
                    return 0;
                }
                return $fechaVencimiento->diffInDays($hoy);
            }
        );
    }

    protected function semaforo(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                $dias = $this->dias_en_mora;
                if (empty($attributes['fecha_vencimiento'])) {
                    return 'gris';
                }
                if ($dias <= 30) {
                    return 'verde';
                } elseif ($dias <= 90) {
                    return 'amarillo';
                } else {
                    return 'rojo';
                }
            }
        );
    }

    public function cooperativa(): BelongsTo { return $this->belongsTo(Cooperativa::class); }
    
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'caso_user');
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function deudor(): BelongsTo { return $this->belongsTo(Persona::class, 'deudor_id')->withTrashed(); }
    
    public function codeudores(): BelongsToMany
    {
        return $this->belongsToMany(Codeudor::class, 'caso_codeudor');
    }
    
    public function codeudor1()
    {
        return $this->belongsToMany(Codeudor::class, 'caso_codeudor')
                    ->orderBy('codeudores.id') 
                    ->limit(1);
    }
    
    public function codeudor2()
    {
        return $this->belongsToMany(Codeudor::class, 'caso_codeudor')
                    ->orderBy('codeudores.id')
                    ->skip(1)
                    ->take(1);
    }

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

    public function actuaciones(): MorphMany
    {
        return $this->morphMany(Actuacion::class, 'actuable')->orderBy('created_at', 'desc');
    }

    public function revisionesDiarias(): MorphMany
    {
        return $this->morphMany(RevisionDiaria::class, 'revisable');
    }

    public function contrato(): HasOne
    {
        return $this->hasOne(Contrato::class, 'caso_id');
    }

    public function tareas(): MorphMany
    {
        return $this->morphMany(Tarea::class, 'tarea');
    }
}