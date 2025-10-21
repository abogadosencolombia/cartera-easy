<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificacionLeida extends Model
{
    use HasFactory;

    protected $table = 'notificaciones_leidas';

    public $timestamps = false; // Esta tabla no necesita created_at/updated_at

    protected $fillable = [
        'user_id',
        'notificacion_id',
        'leido_en',
    ];

    protected $casts = [
        'leido_en' => 'datetime',
    ];
}
