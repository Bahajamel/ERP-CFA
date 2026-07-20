<?php

namespace App\Mail;

use App\Models\CandidatePromotion;
use App\Scolarite\InscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Invite l'apprenant (par email) à choisir ses matières via un lien tokenisé,
 * une fois son admission validée et sa classe attribuée.
 */
class InvitationInscription extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public CandidatePromotion $inscription,
        public string $lien,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Finalisez votre inscription — choisissez vos matières',
        );
    }

    public function content(): Content
    {
        $candidate = $this->inscription->candidate;
        $promotion = $this->inscription->promotion;

        return new Content(
            markdown: 'emails.invitation-inscription',
            with: [
                'prenom' => $candidate?->prenom ?? '',
                'classe' => $promotion?->nom_complet ?? '',
                'formation' => $promotion?->formation?->libelle ?? '',
                'lien' => $this->lien,
                'jours' => InscriptionService::EXPIRATION_JOURS,
            ],
        );
    }
}
