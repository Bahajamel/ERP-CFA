<?php

namespace App\Mail;

use App\Models\DemoRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Prévient l'équipe qu'un prospect a demandé une démonstration depuis le site
 * vitrine. Reprend les informations utiles pour préparer le rappel.
 */
class NouvelleDemandeDemo extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public DemoRequest $demande) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nouvelle demande de démonstration — '.$this->demande->organization_name,
            replyTo: [$this->demande->email],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.nouvelle-demande-demo',
            with: ['demande' => $this->demande],
        );
    }
}
