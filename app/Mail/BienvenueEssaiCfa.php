<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Accueille le prospect dont l'essai gratuit vient d'être ouvert et lui transmet
 * ses accès. Remplace la transmission manuelle des identifiants par l'éditeur.
 *
 * Le mot de passe est un secret transitoire (jamais stocké) : il est passé en
 * clair au constructeur et n'existe que le temps de l'envoi. Il est null quand
 * le compte préexistait (on ne réinitialise alors rien — cf. ProvisionnerCfaEssai).
 */
class BienvenueEssaiCfa extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $nomCfa,
        public string $url,
        public string $email,
        public ?string $motDePasse,
        public ?string $dateFinEssai,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre espace Meridian CFA est prêt — essai gratuit',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.bienvenue-essai-cfa',
            with: [
                'nomCfa' => $this->nomCfa,
                'url' => $this->url,
                'email' => $this->email,
                'motDePasse' => $this->motDePasse,
                'dateFinEssai' => $this->dateFinEssai,
            ],
        );
    }
}
