<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IncidenteJuridico extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'incidentes_juridicos';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'caso_id',
        'usuario_responsable_id',
        'origen',
        'asunto',
        'descripcion',
        'estado',
        'fecha_registro',
    ];

    // --- RELACIONES ---

    // Un incidente puede tener muchos tickets disciplinarios.
    public function tickets()
    {
        return $this->hasMany(TicketDisciplinario::class, 'incidente_id');
    }

    // Un incidente puede tener muchos archivos de evidencia.
    public function archivos()
    {
        return $this->hasMany(ArchivoIncidente::class, 'incidente_id');
    }

    // El incidente es responsabilidad de un usuario.
    public function responsable()
    {
        return $this->belongsTo(User::class, 'usuario_responsable_id');
    }
}