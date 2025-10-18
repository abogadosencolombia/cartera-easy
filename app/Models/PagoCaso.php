<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class PagoCaso extends Model
{
    use HasFactory;

    protected $table = 'pagos_caso';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'caso_id',
        'user_id',
        'monto_pagado',
        'fecha_pago',
        'motivo_pago',
        'comprobante_path', // <-- ¡Importante! Campo para la ruta del archivo
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'fecha_pago' => 'date',
        'monto_pagado' => 'decimal:2',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = ['comprobante_url'];

    // --- RELACIONES ---

    /**
     * A payment belongs to a single Case.
     */
    public function caso(): BelongsTo
    {
        return $this->belongsTo(Caso::class);
    }

    /**
     * Get the user who registered the payment.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // --- ACCESOR ---

    /**
     * Generate a virtual 'comprobante_url' attribute to access the file.
     */
    protected function comprobanteUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->comprobante_path && Storage::disk('private')->exists($this->comprobante_path)) {
                    // Generates the URL using the route we created in web.php
                    return route('pagos.verComprobante', $this);
                }
                return null;
            }
        );
    }
}
