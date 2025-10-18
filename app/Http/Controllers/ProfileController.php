<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Muestra el formulario de perfil del usuario.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        // Rellena todos los datos validados.
        // Esto funciona para el nombre/email y también para las notificaciones.
        $user->fill($request->validated());

        // Si el email fue modificado, se anula la verificación.
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        
        // Aquí es donde manejamos las casillas de notificaciones.
        // Si el formulario de perfil se envía, esta parte no se ejecuta y no borra tus preferencias.
        if ($request->has('preferencias_notificacion')) {
            $preferencias = $request->input('preferencias_notificacion', []);
            // Nos aseguramos de guardar 'true' o 'false'.
            $user->preferencias_notificacion = [
                'email' => !empty($preferencias['email']),
                'system' => !empty($preferencias['system']), // Usamos 'system' como en el validador
            ];
        }

        $user->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Elimina la cuenta del usuario.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /**
     * ===== INICIO DE LA CORRECCIÓN =====
     * Actualiza las preferencias de notificación del usuario.
     */
    public function updatePreferences(Request $request): RedirectResponse
    {
        // Validamos directamente las claves que envía el frontend.
        $validated = $request->validate([
            'preferencias_notificacion' => ['required', 'array'],
            'preferencias_notificacion.in-app' => ['required', 'boolean'],
            'preferencias_notificacion.email' => ['required', 'boolean'],
        ]);

        // Actualizamos al usuario con los datos validados.
        $request->user()->update([
            'preferencias_notificacion' => $validated['preferencias_notificacion'],
        ]);

        return Redirect::route('profile.edit')->with('status', 'notification-preferences-updated');
    }
    // ===== FIN DE LA CORRECCIÓN =====
}