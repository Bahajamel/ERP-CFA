<?php

namespace App\Services;

use App\Documents\FicheBesoin;
use App\Enums\DocumentSource;
use App\Enums\DocumentStatut;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\Need;
use App\Models\QualiopiIndicator;
use Illuminate\Support\Facades\Auth;

/**
 * Génère la fiche besoin d'une offre et l'archive à DEUX titres, sans dupliquer
 * le fichier :
 *
 *  1. comme pièce de l'offre (GED de l'offre), pour retrouver ce qui a été
 *     imprimé et transmis ;
 *  2. comme preuve de l'indicateur Qualiopi n°4 (« Le prestataire analyse le
 *     besoin du bénéficiaire en lien avec l'entreprise »), pour l'audit.
 *
 * Un seul document courant par offre : régénérer remplace le fichier et la date
 * plutôt que d'empiler les versions — une fiche besoin est un état des lieux à
 * l'instant T, pas un document contractuel dont l'historique fait foi (à la
 * différence d'une convention ou d'un CERFA).
 */
class FicheBesoinService
{
    /** Numéro de l'indicateur Qualiopi couvert par la fiche besoin. */
    public const INDICATEUR_QUALIOPI = 4;

    public function __construct(private readonly FicheBesoin $generateur) {}

    /** PDF de la fiche besoin (octets bruts), sans rien enregistrer. */
    public function pdf(Need $need): string
    {
        return $this->generateur->pour($need);
    }

    /** Nom de fichier lisible : « fiche-besoin-BES-2026-0019.pdf ». */
    public function nomFichier(Need $need): string
    {
        return $this->generateur->nomFichier($need);
    }

    /**
     * Génère la fiche, l'archive sur l'offre et la rattache à l'indicateur
     * Qualiopi n°4. Retourne le document GED.
     */
    public function archiver(Need $need): Document
    {
        $pdf = $this->generateur->pour($need);
        $nom = $this->generateur->nomFichier($need);

        $document = $this->documentCourant($need) ?? $need->documents()->make([
            'type' => DocumentType::FicheBesoin->value,
            'source' => DocumentSource::Genere->value,
        ]);

        $document->forceFill([
            // Hors panel (commande, job), le trait ne peut pas déduire le CFA :
            // on aligne le document sur l'offre, sinon il naîtrait invisible.
            'organisation_id' => $need->organisation_id,
            'type' => DocumentType::FicheBesoin->value,
            'statut' => DocumentStatut::Recu->value,
            'source' => DocumentSource::Genere->value,
            'nom_fichier' => 'Fiche besoin '.$this->generateur->reference($need),
            'uploaded_by' => Auth::id(),
        ]);
        $document->documentable()->associate($need);
        $document->save();

        // Remplace le fichier précédent (régénération) puis attache le nouveau.
        $document->clearMediaCollection('fichier');
        $document->addMediaFromString($pdf)->usingFileName($nom)->toMediaCollection('fichier');
        $document->touch();

        $this->rattacherAQualiopi($document);

        return $document->fresh();
    }

    /** Fiche besoin déjà archivée pour cette offre, s'il y en a une. */
    public function documentCourant(Need $need): ?Document
    {
        return $need->documents()
            ->where('type', DocumentType::FicheBesoin->value)
            ->latest('id')
            ->first();
    }

    /**
     * Rattache la fiche à l'indicateur Qualiopi n°4 comme élément de preuve.
     *
     * Le document reste rattaché à l'OFFRE (relation polymorphe) : on ne le
     * déplace pas, on l'ajoute au faisceau de preuves de l'indicateur via une
     * copie légère, pour qu'il apparaisse dans les deux écrans. Si l'indicateur
     * n'existe pas en base (référentiel non initialisé), on ne fait rien plutôt
     * que d'échouer : la fiche reste utilisable.
     */
    private function rattacherAQualiopi(Document $document): void
    {
        $indicateur = QualiopiIndicator::query()
            ->where('numero', self::INDICATEUR_QUALIOPI)
            ->first();

        if ($indicateur === null) {
            return;
        }

        $preuve = $indicateur->documents()
            ->where('type', DocumentType::FicheBesoin->value)
            ->where('nom_fichier', $document->nom_fichier)
            ->first();

        if ($preuve === null) {
            $preuve = $indicateur->documents()->make([
                'type' => DocumentType::FicheBesoin->value,
                'source' => DocumentSource::Genere->value,
            ]);
        }

        $preuve->forceFill([
            'organisation_id' => $document->organisation_id,
            'type' => DocumentType::FicheBesoin->value,
            'statut' => DocumentStatut::Recu->value,
            'source' => DocumentSource::Genere->value,
            'nom_fichier' => $document->nom_fichier,
            'uploaded_by' => Auth::id(),
        ]);
        $preuve->documentable()->associate($indicateur);
        $preuve->save();

        $media = $document->getFirstMedia('fichier');

        if ($media === null) {
            return;
        }

        $preuve->clearMediaCollection('fichier');
        $media->copy($preuve, 'fichier');
        $preuve->touch();
    }
}
