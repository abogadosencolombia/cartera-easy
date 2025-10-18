<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificacionCaso extends Model
{
    protected $table = 'notificaciones_caso';

    protected $fillable = [
        'caso_id','user_id','tipo','mensaje','prioridad',
        'programado_en','fecha_envio','last_sent_at',
        'completed','leido','atendida_en','programado_para',
    ];

    protected $casts = [
        'programado_en' => 'immutable_datetime',
        'fecha_envio'   => 'immutable_datetime',
        'last_sent_at'  => 'immutable_datetime',
        'atendida_en'   => 'immutable_datetime',
        'completed'     => 'boolean',
        'leido'         => 'boolean',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function caso(): BelongsTo { return $this->belongsTo(Caso::class); }
}
