<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;

class ContactoClienteMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Propiedades públicas para pasar datos a la vista del correo.
     */
    public $nombreCliente;
    public $emailCliente;
    public $asuntoCliente;
    public $mensajeCliente;

    /**
     * Create a new message instance.
     */
    public function __construct(string $nombre, string $email, string $asunto, string $mensaje)
    {
        $this->nombreCliente = $nombre;
        $this->emailCliente = $email;
        $this->asuntoCliente = $asunto;
        $this->mensajeCliente = $mensaje;
    }

    /**
     * Get the message envelope.
     * Define el "De", "Para", "Asunto", etc.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            replyTo: [
                new Address($this->emailCliente, $this->nombreCliente)
            ],
            subject: 'Nuevo Mensaje de Cliente: ' . $this->asuntoCliente,
        );
    }

    /**
     * Get the message content definition.
     * Le dice a Laravel qué plantilla de correo usar.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.contacto-cliente',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
