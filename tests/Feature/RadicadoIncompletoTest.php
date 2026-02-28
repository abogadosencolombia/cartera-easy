<?php

use App\Models\User;
use App\Models\Persona;
use App\Models\Juzgado;
use App\Models\TipoProceso;
use App\Models\EtapaProcesal;
use App\Models\ProcesoRadicado;

beforeEach(function () {
    $this->admin = User::factory()->create([
        'tipo_usuario' => 'admin',
        'email_verified_at' => now(),
    ]);
    $this->juzgado = Juzgado::create(['nombre' => 'Juzgado 1 Civil Municipal']);
    $this->tipoProceso = TipoProceso::create(['nombre' => 'Ejecutivo']);
    $this->etapa = EtapaProcesal::create(['nombre' => 'Registro Inicial', 'orden' => 1, 'riesgo' => 'BAJO']);
});

test('puede crear un radicado con demandado por identificar', function () {
    $data = [
        'radicado' => '2026-00001',
        'asunto' => 'Cobro de cartera',
        'abogado_id' => $this->admin->id,
        'juzgado_id' => $this->juzgado->id,
        'tipo_proceso_id' => $this->tipoProceso->id,
        'fecha_proxima_revision' => now()->addDays(15)->toDateString(),
        'demandantes' => [
            ['is_new' => true, 'nombre_completo' => 'Cliente Principal', 'tipo_documento' => 'CC', 'numero_documento' => '123456']
        ],
        'demandados' => [
            ['is_new' => true, 'sin_info' => true, 'tipo_documento' => 'CC']
        ],
    ];

    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($this->admin)
        ->post(route('procesos.store'), $data);

    $response->assertRedirect();
    
    $proceso = ProcesoRadicado::first();
    expect($proceso->info_incompleta)->toBeTrue();
    
    $demandado = $proceso->demandados->first();
    expect($demandado->nombre_completo)->toBe('DEMANDADO POR IDENTIFICAR');
});

test('mantiene info_incompleta si el demandado sigue siendo por identificar al editar', function () {
    // 1. Crear radicado incompleto
    $proceso = ProcesoRadicado::create([
        'radicado' => '2026-00002',
        'abogado_id' => $this->admin->id,
        'tipo_proceso_id' => $this->tipoProceso->id,
        'fecha_proxima_revision' => now()->toDateString(),
        'info_incompleta' => true,
        'estado' => 'ACTIVO'
    ]);
    
    $personaIncompleta = Persona::create([
        'nombre_completo' => 'DEMANDADO POR IDENTIFICAR',
        'tipo_documento' => 'CC',
        'numero_documento' => 'TEMP-12345',
        'es_demandado' => true
    ]);
    
    $proceso->demandados()->attach($personaIncompleta->id, ['tipo' => 'DEMANDADO']);

    // 2. Editar sin cambiar el nombre del demandado
    $data = [
        'radicado' => '2026-00002-MOD',
        'abogado_id' => $this->admin->id,
        'tipo_proceso_id' => $this->tipoProceso->id,
        'fecha_proxima_revision' => now()->addDays(5)->toDateString(),
        'demandantes' => [
            ['id' => Persona::create(['nombre_completo' => 'Dte', 'numero_documento' => 'DTE123'])->id]
        ],
        'demandados' => [
            ['id' => $personaIncompleta->id] // Enviado como existente
        ],
    ];

    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($this->admin)
        ->patch(route('procesos.update', $proceso->id), $data);

    $response->assertRedirect();
    
    $proceso->refresh();
    expect($proceso->info_incompleta)->toBeTrue();
    expect($proceso->radicado)->toBe('2026-00002-MOD');
});

test('quita info_incompleta al completar los datos de un demandado existente', function () {
    // 1. Crear radicado incompleto
    $proceso = ProcesoRadicado::create([
        'radicado' => '2026-00003',
        'abogado_id' => $this->admin->id,
        'tipo_proceso_id' => $this->tipoProceso->id,
        'fecha_proxima_revision' => now()->toDateString(),
        'info_incompleta' => true,
        'estado' => 'ACTIVO'
    ]);
    
    $personaIncompleta = Persona::create([
        'nombre_completo' => 'DEMANDADO POR IDENTIFICAR',
        'tipo_documento' => 'CC',
        'numero_documento' => 'TEMP-12345',
        'es_demandado' => true
    ]);
    
    $proceso->demandados()->attach($personaIncompleta->id, ['tipo' => 'DEMANDADO']);

    // 2. Editar "completando" los datos (is_new = true con el mismo ID)
    $data = [
        'radicado' => '2026-00003',
        'abogado_id' => $this->admin->id,
        'tipo_proceso_id' => $this->tipoProceso->id,
        'fecha_proxima_revision' => now()->addDays(5)->toDateString(),
        'demandantes' => [
            ['id' => Persona::create(['nombre_completo' => 'Dte', 'numero_documento' => 'DTE456'])->id]
        ],
        'demandados' => [
            [
                'id' => $personaIncompleta->id, 
                'is_new' => true, // Flag para indicar que queremos actualizar el registro
                'nombre_completo' => 'Juan Perez', 
                'tipo_documento' => 'CC', 
                'numero_documento' => '80123456',
                'sin_info' => false
            ]
        ],
    ];

    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->actingAs($this->admin)
        ->patch(route('procesos.update', $proceso->id), $data);

    $response->assertRedirect();
    
    $proceso->refresh();
    expect($proceso->info_incompleta)->toBeFalse();
    
    $personaIncompleta->refresh();
    expect($personaIncompleta->nombre_completo)->toBe('Juan Perez');
    expect($personaIncompleta->numero_documento)->toBe('80123456');
});
