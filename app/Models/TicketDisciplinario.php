<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketDisciplinario extends Model
{
    use HasFactory;

    protected $table = 'tickets_disciplinarios';

    protected $fillable = [
        'incidente_id',
        'creado_por',
        'asignado_a',
        'etapa',
        'comentarios',
    ];

    // --- RELACIONES ---

    // Cada ticket pertenece a un único incidente jurídico.
    public function incidente()
    {
        return $this->belongsTo(IncidenteJuridico::class, 'incidente_id');
    }

    // Cada ticket tendrá una única decisión del comité.
    public function decision()
    {
        return $this->hasOne(DecisionComiteEtica::class, 'ticket_id');
    }

    // El ticket fue creado por un usuario.
    public function creador()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    // El ticket está asignado a un usuario para su revisión.
    public function asignado()
    {
        return $this->belongsTo(User::class, 'asignado_a');
    }
}