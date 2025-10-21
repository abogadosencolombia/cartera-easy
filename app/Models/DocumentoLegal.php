<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage; // Importación Clave

class DocumentoLegal extends Model
{
    use HasFactory;

    protected $table = 'documentos_legales';

    protected $fillable = [
        'cooperativa_id',
        'tipo_documento',
        'archivo',
        'fecha_expedicion',
        'fecha_vencimiento'
    ];

    protected $casts = [
        'fecha_expedicion' => 'date',
        'fecha_vencimiento' => 'date',
    ];

    /**
     * Aseguramos que los atributos virtuales 'status' y 'archivo_url'
     * siempre se incluyan cuando se serializa el modelo.
     */
    protected $appends = ['status', 'archivo_url'];

    /**
     * Lógica para determinar el estado del documento (vigente, por vencer, vencido).
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: function () {
                $fechaVencimiento = $this->fecha_vencimiento;

                if (is_null($fechaVencimiento)) {
                    return 'vigente';
                }
                if (Carbon::now()->gt($fechaVencimiento)) {
                    return 'vencido';
                }
                if (Carbon::now()->diffInDays($fechaVencimiento) <= 30) {
                    return 'por_vencer';
                }
                return 'vigente';
            }
        );
    }

    /**
     * ¡NUEVO Y CRUCIAL!
     * Este accesor genera la URL pública y completa del archivo.
     * Esto evita depender del 'storage:link' y soluciona el 404.
     */
    protected function archivoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->archivo ? Storage::url($this->archivo) : null
        );
    }

    public function cooperativa(): BelongsTo
    {
        return $this->belongsTo(Cooperativa::class);
    }
}

