<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntegracionToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'proveedor',
        'api_key',
        'client_id',
        'client_secret',
        'expira_en',
        'activo',
    ];
}