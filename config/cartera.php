<?php
return [
    'users_table' => 'users',
    'cases_table' => 'casos',
    'cooperativas_table' => 'cooperativas',
    'recoveries_table' => 'pagos_caso',

    'user_pk' => 'id',
    'case_pk' => 'id',
    'cooperativa_pk' => 'id',

    'user_name_candidates' => ['name','nombre','nombres'],

    // si NO usas Spatie y tienes columna de rol en users, ponla aquí; si no, deja null
    'role_column' => null,
    'role_values_for_agents' => ['abogado','gestor','admin'],

    'case_user_fk_candidates' => ['user_id','gestor_id','abogado_id','assigned_to_id','responsable_id'],
    'case_number_candidates' => ['referencia_credito','radicado','codigo','numero','id'],
    'case_coop_fk_candidates' => ['cooperativa_id','coop_id'],
    'cooperativa_name_candidates' => ['nombre','razon_social','name'],

    'recovery_case_fk_candidates' => ['caso_id','case_id','proceso_id'],
    'recovery_amount_candidates' => ['monto_pagado','valor_pagado','monto','valor'],

    'frontend_paths' => [
        'cases_base' => '/casos/',
        'cooperativas_base' => '/cooperativas/',
    ],
];
