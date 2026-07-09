<?php

namespace App\Scolarite;

use App\Enums\DocumentStatut;
use App\Enums\DocumentType;
use App\Models\Candidate;
use App\Models\CfaProfile;
use App\Models\Document;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Bulletin de notes d'un apprenant : agrège ses évaluations en moyennes par
 * matière (pondérées par coefficient) + moyenne générale, adjoint l'assiduité,
 * et produit un PDF (dompdf) archivable en GED.
 */
class BulletinGenerator
{
    /** Données du bulletin (réutilisées par la vue et l'aperçu écran). */
    public function donnees(Candidate $candidate): array
    {
        $matieres = $candidate->moyennesParMatiere();
        $assiduite = $candidate->assiduite();

        return [
            'cfa' => CfaProfile::current(),
            'apprenant' => $candidate,
            'classe' => $candidate->promotions->first(),
            'formation' => $candidate->formationVisee,
            'matieres' => $matieres,
            'moyenne_generale' => $candidate->moyenneGenerale(),
            'assiduite' => $assiduite['taux'],
            'absences_injustifiees' => $assiduite['absences_injustifiees'],
            'edite_le' => now(),
        ];
    }

    /** Rend le bulletin en PDF (octets bruts). */
    public function pdf(Candidate $candidate): string
    {
        return Pdf::loadView('pdf.bulletin', ['d' => $this->donnees($candidate)])
            ->setPaper('a4')
            ->output();
    }

    /**
     * Génère le bulletin et l'archive en GED (document typé « Bulletin »),
     * versionné : un nouveau bulletin succède au précédent sans l'effacer.
     */
    public function archiver(Candidate $candidate, ?int $userId = null): Document
    {
        $document = $this->nouveauDocument($candidate, 'Bulletin '.now()->format('d/m/Y'), $userId);

        $document->addMediaFromString($this->pdf($candidate))
            ->usingFileName('bulletin-'.$candidate->id.'-v'.$document->version.'.pdf')
            ->toMediaCollection('fichier');

        return $document;
    }

    /**
     * Importe un bulletin externe (PDF déposé par l'administrateur) et l'archive
     * en GED, versionné comme un bulletin généré.
     */
    public function importer(Candidate $candidate, string $cheminDisk, ?string $nomOriginal = null, ?int $userId = null): Document
    {
        $document = $this->nouveauDocument(
            $candidate,
            'Bulletin importé — '.($nomOriginal ?: now()->format('d/m/Y')),
            $userId,
        );

        $document->addMediaFromDisk($cheminDisk, 'public')->toMediaCollection('fichier');

        return $document;
    }

    /** Crée le document GED « Bulletin » suivant (version chaînée à la précédente). */
    private function nouveauDocument(Candidate $candidate, string $nomFichier, ?int $userId): Document
    {
        $precedent = $candidate->documents()
            ->where('type', DocumentType::Bulletin->value)
            ->latest('version')
            ->latest('id')
            ->first();

        return $candidate->documents()->create([
            'type' => DocumentType::Bulletin->value,
            'statut' => DocumentStatut::Recu->value,
            'nom_fichier' => $nomFichier,
            'version' => ($precedent?->version ?? 0) + 1,
            'previous_version_id' => $precedent?->id,
            'uploaded_by' => $userId,
        ]);
    }
}
