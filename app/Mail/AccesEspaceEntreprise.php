<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Envoie à l'entreprise le lien personnel vers son espace (portail sans mot de
 * passe) : alternants, assiduité, documents, factures. Lien à ne pas partager.
 */
class AccesEspaceEntreprise extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Company $company,
        public string $lien,
        public string $nomCfa,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre espace entreprise — '.$this->nomCfa,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.acces-espace-entreprise',
            with: [
                'entreprise' => $this->company->raison_sociale,
                'lien' => $this->lien,
                'nomCfa' => $this->nomCfa,
            ],
        );
    }
}
