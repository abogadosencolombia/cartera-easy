<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DecisionComiteEtica extends Model
{
    use HasFactory;

    protected $table = 'decisiones_comite_etica';

    protected $fillable = [
        'ticket_id',
        'revisado_por',
        'resumen_decision',
        'resultado',
        'fecha_revision',
        'medida_administrativa',
        'requiere_capacitacion',
        'caso_reasignado',
    ];

    // --- RELACIONES ---

    // La decisión pertenece a un ticket disciplinario.
    public function ticket()
    {
        return $this->belongsTo(TicketDisciplinario::class, 'ticket_id');
    }

    // La decisión fue registrada por un usuario revisor.
    public function revisor()
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }
}