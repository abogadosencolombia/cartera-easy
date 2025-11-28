<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | CORS (Cross-Origin Resource Sharing)
    |--------------------------------------------------------------------------
    |
    | Aquí puedes configurar tu política de CORS.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', '/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://cobrocartera.abogadosencolombiasas.com', // <-- AÑADIDO
        // 'http://localhost:5173',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    /*
    |--------------------------------------------------------------------------
    | Soporte de Credenciales
    |--------------------------------------------------------------------------
    |
    | Si tu frontend necesita enviar cookies (como la sesión de Laravel)
    | al hacer peticiones, debes poner esto en 'true'.
    | Cuando 'supports_credentials' es 'true', 'allowed_origins' NO PUEDE
    | ser ['*'] (asterisco). Debes especificar los dominios.
    |
    */

    'supports_credentials' => true,

];