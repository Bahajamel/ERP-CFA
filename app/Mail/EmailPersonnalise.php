<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail libre (objet + corps) issu d'un « mail type » édité par le commercial,
 * variables déjà résolues. Rendu en texte simple mis en forme (sauts de ligne
 * préservés) — pas de HTML arbitraire côté saisie.
 */
class EmailPersonnalise extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $sujet,
        public string $corps,
        public ?string $expediteur = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->sujet);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.personnalise',
            with: [
                'corps' => $this->corps,
                'expediteur' => $this->expediteur,
            ],
        );
    }
}
