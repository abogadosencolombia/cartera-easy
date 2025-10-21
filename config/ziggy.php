<?php

return [
    'groups' => [
        // Incluye todas las rutas que tengan el nombre 'admin.*'
        // Esto asegura que todas tus rutas de administración, incluida la nueva,
        // estarán disponibles en el frontend.
        'admin' => ['admin.*'],

        // Aquí puedes añadir otros grupos de rutas que necesites en el frontend
        // por ejemplo, las de honorarios.
        'app' => [
            'login', 
            'dashboard', 
            'profile.*', 
            'honorarios.contratos.*',
            // Añade aquí cualquier otra ruta individual o con comodines que necesites
        ],
    ],
];
