<?php

namespace App\Mail;

use App\Models\NotificacionCaso;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificacionCasoMail extends Mailable
{
    use Queueable, SerializesModels;

    // Propiedad pública para guardar la notificación que vamos a enviar
    public $notificacion;

    /**
     * Create a new message instance.
     *
     * @param NotificacionCaso $notificacion La notificación que se enviará
     */
    public function __construct(NotificacionCaso $notificacion)
    {
        // Asignamos la notificación recibida a nuestra propiedad pública
        $this->notificacion = $notificacion;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        // Aquí definimos el asunto del correo.
        return new Envelope(
            subject: 'Nueva Alerta de Caso: ' . $this->notificacion->caso->nombre_caso
        );
    }

    /**
     * Get the message content definition.
     *
     * @return \Illuminate\Mail\Mailables\Content
     */
    public function content()
    {
        // Aquí le decimos a Laravel que use nuestra vista de Markdown.
        return new Content(
            markdown: 'emails.notificaciones.caso',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array
     */
    public function attachments()
    {
        return [];
    }
}