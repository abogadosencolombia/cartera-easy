<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\NuevoUsuarioPendienteMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',            
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'tipo_usuario' => 'cliente',
            'estado_activo' => false,
            'preferencias_notificacion' => [
                'email' => true,
                'in-app' => true,
            ],
        ]);

        $adminEmails = User::where('tipo_usuario', 'admin')
            ->where('estado_activo', true)
            ->whereNotNull('email')
            ->pluck('email')
            ->push(config('mail.admin_address'))
            ->filter(fn ($email) => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();

        if ($adminEmails->isEmpty()) {
            Log::warning('Nueva cuenta pendiente sin administradores activos para notificar.', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        }

        foreach ($adminEmails as $adminEmail) {
            try {
                Mail::to($adminEmail)->send(new NuevoUsuarioPendienteMail(
                    $user,
                    route('admin.users.edit', $user)
                ));
            } catch (\Throwable $exception) {
                Log::error('No se pudo enviar notificación de usuario pendiente.', [
                    'admin_email' => $adminEmail,
                    'pending_user_id' => $user->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        Log::info('Notificaciones de usuario pendiente procesadas.', [
            'pending_user_id' => $user->id,
            'recipients_count' => $adminEmails->count(),
        ]);

        return redirect()
            ->route('login')
            ->with('status', 'Solicitud recibida. Un administrador debe revisar y activar la cuenta antes de que puedas ingresar.');
    }
}
