<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Le decimos a Laravel que no pida el token CSRF para esta ruta.
        'chatbot/notify',
        'api/webhook/chatwoot',
        'chatwoot-proxy/*',
        'app/*',
        'auth/*',
        'api/v1/*',
        'rails/*',
        'vite/*',
        'assets/*',
        'cable/*',
        'storage/*',
        'packs/*',
    ];
}