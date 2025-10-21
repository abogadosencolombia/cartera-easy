<?php

namespace App\Http\Controllers;

use App\Mail\ContactoClienteMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactoClienteController extends Controller
{
    /**
     * Maneja el envío del formulario de contacto del cliente.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function enviar(Request $request): RedirectResponse
    {
        // 1. Validar los datos del formulario de manera robusta.
        $validated = $request->validate([
            'asunto' => ['required', 'string', 'max:255'],
            'mensaje' => ['required', 'string', 'max:5000'],
        ]);

        // 2. Obtener el usuario autenticado.
        $user = Auth::user();

        // 3. Obtener el destinatario DIRECTAMENTE del archivo .env para evitar problemas de caché.
        $recipientEmail = env('MAIL_TO_ADMIN_ADDRESS');

        // 4. Medida de seguridad crítica: si no hay un destinatario configurado, no continuar.
        if (empty($recipientEmail)) {
            Log::error('Intento de envío de correo de cliente fallido: La variable MAIL_TO_ADMIN_ADDRESS no está configurada en el archivo .env.');
            
            // Devolver un error específico que el usuario pueda entender.
            return back()->withErrors(['general' => 'No se ha podido enviar el mensaje. El sistema no tiene un destinatario configurado.']);
        }
        
        try {
            // 5. Enviar el correo usando la clase Mailable.
            Mail::to($recipientEmail)
                ->send(new ContactoClienteMail(
                    $user->name,
                    $user->email,
                    $validated['asunto'],
                    $validated['mensaje']
                ));

        } catch (\Exception $e) {
            // Si hay un error con la configuración del servidor de correo, lo registramos.
            Log::error('Error al enviar correo de contacto de cliente: ' . $e->getMessage());
            return back()->withErrors(['general' => 'No se pudo enviar el correo debido a un error de configuración del servidor. Por favor, verifique las credenciales de correo en el archivo .env.']);
        }

        // 6. Redirigir de vuelta con un mensaje de éxito.
        return redirect()->route('dashboard')->with('success', '¡Mensaje enviado correctamente! Nos pondremos en contacto contigo pronto.');
    }
}