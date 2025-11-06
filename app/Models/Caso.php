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
// --- INICIO: AÑADIR IMPORT REVISION DIARIA ---
use App\Models\RevisionDiaria;
// --- FIN: AÑADIR IMPORT REVISION DIARIA ---

// --- INICIO: AÑADIDO PARA CODEUDORES ---
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
// Asumo que tu modelo Codeudor.php está en App\Models
use App\Models\Codeudor; 
// --- FIN: AÑADIDO PARA CODEUDORES ---


class Caso extends Model
{
    use HasFactory; // Line 16

    // Line 17 is now truly empty

    protected $fillable = [ // Line 18
        'cooperativa_id', 'user_id', 'deudor_id', 
        
        // --- INICIO: CAMBIO ---
        // 'codeudor1_id', 'codeudor2_id', // <-- ELIMINADOS
        // --- FIN: CAMBIO ---

        'referencia_credito', 'radicado', // <-- 'radicado' ya estaba
        
        'tipo_proceso', 'estado_proceso', 'tipo_garantia_asociada',
        'fecha_apertura', 'fecha_vencimiento', 'monto_total', 'tasa_interes_corriente',
        
        // --- INICIO: CAMBIO TASA ---
        'fecha_tasa_interes', // <-- AÑADIDO
        // 'tasa_moratoria', // <-- ELIMINADO
        // --- FIN: CAMBIO TASA ---

        'origen_documental', 'bloqueado', 'motivo_bloqueo',
        'etapa_actual', 'medio_contacto', 'ultima_actividad', 'notas_legales',
        'clonado_de_id',
        'subtipo_proceso',

        // --- INICIO: CORRECCIÓN L4 (AÑADIDO) ---
        'subproceso', // <-- AÑADIDO PARA GUARDAR EL NIVEL 4
        // --- FIN: CORRECCIÓN L4 ---

        'etapa_procesal',
        'juzgado_id', 'especialidad_id',
        'estado',
    ];

    protected $casts = [
        'fecha_apertura' => 'date',
        'fecha_vencimiento' => 'date',
        'monto_total' => 'decimal:2',
        'tasa_interes_corriente' => 'decimal:2',
        
        // --- INICIO: CAMBIO TASA ---
        // 'tasa_moratoria' => 'decimal:2', // <-- ELIMINADO
        'fecha_tasa_interes' => 'date', // <-- AÑADIDO
        // --- FIN: CAMBIO TASA ---

        'bloqueado' => 'boolean',
        'ultima_actividad' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     * Laravel convertirá automáticamente camelCase (diasEnMora) a snake_case (dias_en_mora).
     */
    protected $appends = ['semaforo', 'dias_en_mora'];

    // --- ACCESSORS CORREGIDOS ---

    /**
     * Calcula los días que han pasado desde la fecha de vencimiento.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function diasEnMora(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                // Si no hay fecha de vencimiento, no hay mora.
                if (empty($attributes['fecha_vencimiento'])) {
                    return 0;
                }

                $fechaVencimiento = Carbon::parse($attributes['fecha_vencimiento']);
                $hoy = Carbon::now();

                // Si la fecha de vencimiento aún no ha llegado, la mora es 0.
                if ($fechaVencimiento->isFuture()) {
                    return 0;
                }

                // Si ya pasó, calcula la diferencia de días.
                return $fechaVencimiento->diffInDays($hoy);
            }
        );
    }

    /**
     * Determina el color del semáforo basado en los días en mora.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function semaforo(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                // Este accessor depende del cálculo de 'dias_en_mora'.
                // Lo podemos llamar directamente con $this->dias_en_mora.
                $dias = $this->dias_en_mora;

                if (empty($attributes['fecha_vencimiento'])) {
                    return 'gris'; // Un color por defecto si no hay fecha.
                }

                if ($dias <= 30) {
                    return 'verde'; // Al día o con mora temprana.
                } elseif ($dias <= 90) {
                    return 'amarillo'; // Mora moderada.
                } else {
                    return 'rojo'; // Mora alta.
                }
            }
        );
    }


    // --- RELACIONES ---
    public function cooperativa(): BelongsTo { return $this->belongsTo(Cooperativa::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function deudor(): BelongsTo { return $this->belongsTo(Persona::class, 'deudor_id'); }
    
    // --- INICIO: CAMBIO ---
    // public function codeudor1(): BelongsTo { return $this->belongsTo(Persona::class, 'codeudor1_id');} // <-- ELIMINADO
    // public function codeudor2(): BelongsTo { return $this->belongsTo(Persona::class, 'codeudor2_id');} // <-- ELIMINADO

    /**
     * Relación específica para obtener solo los codeudores
     * de la NUEVA tabla 'codeudores'.
     */
    public function codeudores(): BelongsToMany
    {
        return $this->belongsToMany(Codeudor::class, 'caso_codeudor');
    }
    // --- FIN: CAMBIO ---
    
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

    // --- INICIO: AÑADIR RELACIÓN DE ACTUACIONES ---
    /**
     * Get all of the caso's actuations.
     * Se ordenan de la más reciente a la más antigua por defecto.
     */
    public function actuaciones(): MorphMany
    {
        return $this->morphMany(Actuacion::class, 'actuable')->orderBy('created_at', 'desc');
    }
    // --- FIN: AÑADIR RELACIÓN DE ACTUACIONES ---

    // --- INICIO: AÑADIR RELACIÓN DE REVISIÓN DIARIA ---
    /**
     * Obtiene todas las revisiones diarias para este caso.
     */
    public function revisionesDiarias(): MorphMany
    {
        return $this->morphMany(RevisionDiaria::class, 'revisable');
    }
    // --- FIN: AÑADIR RELACIÓN DE REVISIÓN DIARIA ---

    // ===== ¡ESTA ES LA RELACIÓN IMPORTANTE QUE FALTA EN TU SERVIDOR! =====
    /**
     * Obtiene el contrato de honorarios asociado a este caso.
     */
    public function contrato(): HasOne
    {
        // Esto asume que la llave foránea 'caso_id' está en la tabla 'contratos'
        return $this->hasOne(Contrato::class, 'caso_id');
    }
    // ====================================================================
}

