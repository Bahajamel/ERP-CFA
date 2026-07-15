<?php

namespace App\Mail;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Envoi du CERFA et de la convention aux parties, pour signature manuscrite.
 *
 * Circuit retenu (volontairement sans prestataire de signature électronique,
 * dont l'abonnement API ne se justifie pas au volume du CFA) : le CFA envoie
 * les documents, chaque partie imprime, signe, scanne et renvoie. Le scan
 * signé est ensuite déposé dans l'ERP, qui clôt le contrat.
 *
 * Les PDF sont attachés au mail : le destinataire n'a aucun compte à créer ni
 * aucun lien à suivre.
 */
class DocumentsASigner extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, string>  $pieces  nom de fichier => contenu PDF
     */
    public function __construct(
        public Contract $contract,
        public array $pieces,
        public string $messagePersonnel = '',
        public ?string $destinataire = null,
    ) {}

    public function envelope(): Envelope
    {
        $apprenti = $this->contract->candidate?->nom_complet ?? 'l\'apprenti';

        return new Envelope(
            subject: "Contrat d'apprentissage à signer — {$apprenti}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.documents-a-signer',
            with: [
                'destinataire' => $this->destinataire,
                'apprenti' => $this->contract->candidate?->nom_complet ?? '',
                'entreprise' => $this->contract->company?->raison_sociale ?? '',
                'formation' => $this->contract->formation?->libelle ?? '',
                'dateDebut' => $this->contract->date_debut?->format('d/m/Y'),
                'message' => $this->messagePersonnel,
                'cfa' => config('cfa.nom', 'le CFA'),
                'pieces' => array_keys($this->pieces),
            ],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return collect($this->pieces)
            ->map(fn (string $pdf, string $nom): Attachment => Attachment::fromData(fn (): string => $pdf, $nom)
                ->withMime('application/pdf'))
            ->values()
            ->all();
    }
}
