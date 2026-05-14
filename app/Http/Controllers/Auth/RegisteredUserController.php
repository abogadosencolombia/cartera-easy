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

        $admins = User::where('tipo_usuario', 'admin')
            ->where('estado_activo', true)
            ->whereNotNull('email')
            ->get();

        if ($admins->isEmpty()) {
            Log::warning('Nueva cuenta pendiente sin administradores activos para notificar.', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        }

        foreach ($admins as $admin) {
            try {
                Mail::to($admin->email)->send(new NuevoUsuarioPendienteMail(
                    $user,
                    route('admin.users.edit', $user)
                ));
            } catch (\Throwable $exception) {
                Log::error('No se pudo enviar notificación de usuario pendiente.', [
                    'admin_id' => $admin->id,
                    'pending_user_id' => $user->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return redirect()
            ->route('login')
            ->with('status', 'Solicitud recibida. Un administrador debe revisar y activar la cuenta antes de que puedas ingresar.');
    }
}
