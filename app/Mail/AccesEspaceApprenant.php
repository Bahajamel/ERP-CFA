<?php

namespace App\Mail;

use App\Models\Candidate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Envoie à l'apprenant le lien personnel vers son espace (portail sans mot de
 * passe) : planning, présences, documents. Lien à ne pas partager.
 */
class AccesEspaceApprenant extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Candidate $candidate,
        public string $lien,
        public string $nomCfa,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre espace personnel — '.$this->nomCfa,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.acces-espace-apprenant',
            with: [
                'prenom' => $this->candidate->prenom ?? '',
                'lien' => $this->lien,
                'nomCfa' => $this->nomCfa,
            ],
        );
    }
}
