<?php

namespace App\Models; // <-- ¡ESTA ES LA CORRECCIÓN!

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Codeudor extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * La tabla asociada con el modelo.
     *
     * @var string
     */
    protected $table = 'codeudores';

    /**
     * Los atributos que se pueden asignar masivamente.
     *
     * @var array
     */
    protected $fillable = [
        'nombre_completo',
        'tipo_documento',
        'numero_documento',
        'celular',
        'correo',
        'addresses',      // JSONB
        'social_links',   // JSON
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'addresses' => 'array',
        'social_links' => 'array',
    ];

    /**
     * Los casos a los que este codeudor está asociado.
     */
    public function casos(): BelongsToMany
    {
        return $this->belongsToMany(Caso::class, 'caso_codeudor', 'codeudor_id', 'caso_id');
    }
}

