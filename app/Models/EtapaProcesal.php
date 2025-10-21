<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EtapaProcesal extends Model
{
    use HasFactory;

    protected $table = 'etapas_procesales';
    protected $fillable = ['nombre','orden','descripcion'];
}
