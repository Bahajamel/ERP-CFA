<?php

namespace App\Mail;

use App\Models\Need;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Proposition de candidats envoyée au contact (responsable / RH) de l'entreprise
 * partenaire, récupéré depuis le CRM (contact du besoin, ou contact principal de
 * l'entreprise). Contient le message du commercial et le résumé des profils.
 */
class PropositionCandidats extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  Collection<int, \App\Models\Candidate>  $candidats
     */
    public function __construct(
        public Need $need,
        public Collection $candidats,
        public string $messagePersonnel,
        public ?string $responsable = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Proposition de candidats — '.$this->need->intitule_poste,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.proposition-candidats',
            with: [
                'poste' => $this->need->intitule_poste,
                'entreprise' => $this->need->company?->raison_sociale ?? '',
                'message' => $this->messagePersonnel,
                'candidats' => $this->candidats,
                'responsable' => $this->responsable,
            ],
        );
    }
}
