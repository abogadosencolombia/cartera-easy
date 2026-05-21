<?php

use App\Models\User;

test('admin can reject and permanently delete a pending registration', function () {
    $admin = User::factory()->create([
        'tipo_usuario' => 'admin',
        'estado_activo' => true,
    ]);

    $pendingUser = User::factory()->create([
        'tipo_usuario' => 'cliente',
        'estado_activo' => false,
    ]);

    $response = $this->actingAs($admin)
        ->delete(route('admin.users.reject-pending', $pendingUser, absolute: false));

    $response->assertRedirect(route('admin.users.index', absolute: false));

    expect(User::withTrashed()->find($pendingUser->id))->toBeNull();
});

test('admin cannot reject an active user', function () {
    $admin = User::factory()->create([
        'tipo_usuario' => 'admin',
        'estado_activo' => true,
    ]);

    $activeUser = User::factory()->create([
        'tipo_usuario' => 'cliente',
        'estado_activo' => true,
    ]);

    $response = $this->actingAs($admin)
        ->delete(route('admin.users.reject-pending', $activeUser, absolute: false));

    $response->assertRedirect(route('admin.users.index', absolute: false));

    expect(User::find($activeUser->id))->not->toBeNull();
});
