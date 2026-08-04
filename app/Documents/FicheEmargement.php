<?php

namespace App\Documents;

use App\Models\Organisation;
use App\Models\Seance;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;

/**
 * Génère la FICHE D'ÉMARGEMENT imprimable (A4) d'une séance — la preuve papier à
 * faire signer en séance, puis à rescanner via FeuilleEmargementAction.
 *
 * PROTO (EPIC-14) : s'appuie sur l'existant (Seance + Presence), sans nouveau
 * modèle. Les colonnes de signature (matin / après-midi) sont volontairement
 * vides : la fiche est imprimée puis signée à la main. Une signature électronique
 * pourra s'y greffer plus tard sans changer ce générateur.
 */
class FicheEmargement
{
    /** Octets bruts du PDF (pattern projet : ->output(), jamais ->download()). */
    public function pour(Seance $seance): string
    {
        return Pdf::loadView('pdf.fiche-emargement', ['d' => $this->donnees($seance)])
            ->setPaper('a4', 'portrait')
            ->output();
    }

    public function nomFichier(Seance $seance): string
    {
        $date = $seance->date?->format('Y-m-d') ?? 'sans-date';

        return "fiche-emargement-{$date}-seance-{$seance->getKey()}.pdf";
    }

    /**
     * @return array<string, mixed>
     */
    public function donnees(Seance $seance): array
    {
        $cfa = Organisation::courante();
        $promotion = $seance->promotion;
        $formation = $promotion?->formation;

        $presences = $seance->presences()
            ->with('candidate', 'media')
            ->get()
            ->sortBy(fn ($p) => $p->candidate?->nom.' '.$p->candidate?->prenom);

        $lignes = $presences->values()->map(function ($p, int $i): array {
            $statut = $p->statut;

            return [
                'num' => $i + 1,
                'apprenant' => $p->candidate?->nom_complet ?? '—',
                'statut' => $statut?->getLabel() ?? 'Non renseigné',
                'couleur' => $this->couleur($statut?->getColor()),
                'commentaire' => $p->commentaire,
                'signature' => $p->signatureDataUri(),
                'signe_a' => $p->signed_at,
                // Adresse IP du signataire : preuve exigée par les OPCO pour une
                // signature électronique (avec l'horodatage). Captée à la signature.
                'signe_ip' => $p->signed_ip,
            ];
        });

        return [
            'cfa' => $cfa,
            'logo' => $this->logo($cfa),
            'formation' => $formation,
            'promotion' => $promotion,
            'seance' => $seance,
            'formateur' => $seance->formateur,
            'horaires' => $this->horaires($seance),
            'lieu' => $promotion?->lieuFormationLisible(),
            'lignes' => $lignes,
            'edite_le' => now(),
        ];
    }

    /** « 09:00 – 12:30 » (les horaires sont stockés en time, ex. « 09:00:00 »). */
    private function horaires(Seance $seance): ?string
    {
        $fmt = fn (?string $h): ?string => $h === null ? null : Carbon::parse($h)->format('H\hi');

        $debut = $fmt($seance->heure_debut);
        $fin = $fmt($seance->heure_fin);

        return match (true) {
            $debut !== null && $fin !== null => "{$debut} – {$fin}",
            $debut !== null => $debut,
            default => null,
        };
    }

    /** Logo du CFA en data-URI base64 (dompdf ne suit pas les URL). */
    private function logo(Organisation $cfa): ?string
    {
        $media = $cfa->getFirstMedia('logo');

        if ($media === null || ! is_file($media->getPath())) {
            return null;
        }

        return 'data:'.$media->mime_type.';base64,'.base64_encode(file_get_contents($media->getPath()));
    }

    /** Traduit la couleur sémantique Filament du statut en hex pour le PDF. */
    private function couleur(?string $color): string
    {
        return match ($color) {
            'success' => '#059669',
            'warning' => '#d97706',
            'info' => '#2563eb',
            'danger' => '#dc2626',
            default => '#6b7280',
        };
    }
}
