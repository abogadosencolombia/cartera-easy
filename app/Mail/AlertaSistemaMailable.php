<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Address;

class AlertaSistemaMailable extends Mailable
{
    use Queueable, SerializesModels;

    public $nombreUsuario;
    public $titulo;
    public $mensaje;
    public $link;
    public $detalles;

    public function __construct($nombreUsuario, $titulo, $mensaje, $link, $detalles = null)
    {
        $this->nombreUsuario = $nombreUsuario;
        $this->titulo = $titulo;
        $this->mensaje = $mensaje;
        $this->link = $link;
        $this->detalles = $detalles;
    }

    public function envelope(): Envelope
    {
        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name');

        return new Envelope(
            // Quitamos el emoji del asunto por ahora, a veces Outlook/Gmail lo marcan como promo/spam
            subject: 'Notificación Legal: ' . $this->titulo,
            from: new Address($fromAddress, $fromName)
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.alerta',      // Versión HTML bonita
            text: 'emails.alerta_text'  // Versión Texto Plano (NUEVO: Ayuda al Anti-Spam)
        );
    }

    public function attachments(): array
    {
        return [];
    }
}