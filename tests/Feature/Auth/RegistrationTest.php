<?php

use App\Mail\NuevoUsuarioPendienteMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users register as inactive and admins are notified', function () {
    Mail::fake();

    $admin = User::factory()->create([
        'email' => 'admin@example.com',
        'tipo_usuario' => 'admin',
        'estado_activo' => true,
    ]);

    config(['mail.admin_address' => 'owner@example.com']);

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertGuest();
    $response->assertRedirect(route('login', absolute: false));

    $pendingUser = User::where('email', 'test@example.com')->first();

    expect($pendingUser)->not->toBeNull()
        ->and($pendingUser->estado_activo)->toBeFalse()
        ->and($pendingUser->tipo_usuario)->toBe('cliente');

    Mail::assertSent(NuevoUsuarioPendienteMail::class, 2);
    Mail::assertSent(NuevoUsuarioPendienteMail::class, fn ($mail) => $mail->hasTo($admin->email));
    Mail::assertSent(NuevoUsuarioPendienteMail::class, fn ($mail) => $mail->hasTo('owner@example.com'));
});
