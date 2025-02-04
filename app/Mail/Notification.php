<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class Notification extends Mailable
{
    use Queueable, SerializesModels;

    public $data; // Variable para almacenar los datos del correo

    /**
     * Create a new message instance.
     */
    public function __construct($data)
    {
        $this->data = $data; // Guardamos los datos en la propiedad
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Rossi equipamientos - Nueva solicitud web', // Asunto del correo
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'email', // Nombre de la vista
            with: ['data' => $this->data] // Pasamos los datos a la vista
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}

