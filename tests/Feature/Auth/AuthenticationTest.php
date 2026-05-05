<?php

use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('login does not create persistent remember sessions', function () {
    $user = User::factory()->create([
        'remember_token' => 'old-token',
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
        'remember' => true,
    ]);

    $user->refresh();

    $this->assertAuthenticated();
    expect($user->remember_token)->not->toBe('old-token');
});

test('stale sessions are closed when the same user logs in elsewhere', function () {
    config(['session.single_active' => true]);

    $user = User::factory()->create([
        'active_session_id' => 'session-from-another-device',
    ]);

    $response = $this
        ->withSession(['active_session_token' => 'current-browser-session'])
        ->actingAs($user)
        ->get('/profile');

    $this->assertGuest();
    $response->assertRedirect(route('login', absolute: false));
    $response->assertSessionHas(
        'status',
        'Tu sesión se cerró porque esta cuenta inició sesión en otro equipo.'
    );
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});
