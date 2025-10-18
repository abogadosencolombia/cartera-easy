<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;

class ContactoClienteController extends Controller
{
    /**
     * Envía el correo de contacto del cliente a la firma de abogados.
     */
    public function enviar(Request $request): RedirectResponse
    {
        $request->validate([
            'asunto' => 'required|string|max:255',
            'mensaje' => 'required|string|max:5000',
        ]);

        $usuarioCliente = Auth::user();
        $emailFirma = 'abogadosencolombiasas@gmail.com'; // El email que me proporcionaste

        $contenido = [
            'nombreCliente' => $usuarioCliente->name,
            'emailCliente' => $usuarioCliente->email,
            'asunto' => $request->input('asunto'),
            'mensaje' => $request->input('mensaje'),
        ];

        // Usamos una función anónima para enviar un correo simple
        Mail::raw(
            "Nombre del Cliente: {$contenido['nombreCliente']} ({$contenido['emailCliente']})\n\n" .
            "Asunto: {$contenido['asunto']}\n\n" .
            "Mensaje:\n{$contenido['mensaje']}",
            function ($message) use ($emailFirma, $contenido) {
                $message->to($emailFirma)
                        ->subject('Nuevo Mensaje de Contacto: ' . $contenido['asunto'])
                        ->from(config('mail.from.address'), config('mail.from.name'));
            }
        );

        return back()->with('success', '¡Tu mensaje ha sido enviado! Nos pondremos en contacto contigo pronto.');
    }
}