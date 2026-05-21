<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NuevoUsuarioPendienteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $pendingUser,
        public string $approvalUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: 'Nueva cuenta pendiente de aprobación',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.nuevo-usuario-pendiente',
            text: 'emails.nuevo-usuario-pendiente-text',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
