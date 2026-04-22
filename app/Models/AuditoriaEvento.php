<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditoriaEvento extends Model
{
    use HasFactory;

    protected $table = 'auditoria_eventos';

    protected $fillable = [
        'user_id',
        'evento',
        'descripcion_breve',
        'auditable_id',
        'auditable_type',
        'criticidad',
        'detalle_anterior',
        'detalle_nuevo',
        'direccion_ip',
        'user_agent',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'detalle_anterior' => 'array',
        'detalle_nuevo' => 'array',
    ];

    /**
     * Humaniza los detalles para que el frontend no muestre IDs.
     */
    public function getDetalleNuevoAttribute($value)
    {
        return $this->humanizeDetails(json_decode($value, true));
    }

    public function getDetalleAnteriorAttribute($value)
    {
        return $this->humanizeDetails(json_decode($value, true));
    }

    private function humanizeDetails($details)
    {
        if (!$details || !is_array($details)) return $details;

        $humanized = [];
        foreach ($details as $key => $val) {
            $newVal = $val;
            if (str_ends_with($key, '_id') && is_numeric($val)) {
                try {
                    $table = str_replace('_id', '', $key);
                    $tableName = match($table) {
                        'user', 'abogado', 'responsable_revision', 'created_by' => 'users',
                        'deudor', 'persona', 'cliente' => 'personas',
                        'tipo_proceso', 'tipo_proceso_id' => 'tipos_proceso',
                        'subtipo_proceso' => 'subtipos_proceso',
                        'subproceso' => 'subprocesos',
                        'juzgado', 'juzgado_id' => 'juzgados',
                        'cooperativa', 'cooperativa_id' => 'cooperativas',
                        'especialidad', 'especialidad_id' => 'especialidades_juridicas',
                        'etapa_procesal', 'etapa_procesal_id', 'etapa_actual_id' => 'etapas_procesales',
                        default => \Illuminate\Support\Str::plural($table)
                        };
                    if (\Illuminate\Support\Facades\Schema::hasTable($tableName)) {
                        $nameField = in_array($tableName, ['users', 'personas']) ? 
                            ($tableName === 'users' ? 'name' : 'nombre_completo') : 'nombre';
                        
                        $name = \Illuminate\Support\Facades\DB::table($tableName)
                            ->where('id', $val)
                            ->value($nameField);
                        
                        $newVal = $name ? "$name (ID: $val)" : $val;
                    }
                } catch (\Exception $e) {}
            }
            $humanized[$key] = $newVal;
        }
        return $humanized;
    }

    /**
     * Get the user that performed the action.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the parent auditable model (Caso, Documento, etc.).
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
