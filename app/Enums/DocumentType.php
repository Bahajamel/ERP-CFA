<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DocumentType: string implements HasLabel
{
    case PieceIdentite = 'piece_identite';
    case CvCandidat = 'cv_candidat';
    case CarteVitale = 'carte_vitale';
    case DiplomeBulletins = 'diplome_bulletins';
    case CvMaitreApprentissage = 'cv_maitre_apprentissage';
    case TestPositionnement = 'test_positionnement';
    case Contrat = 'contrat';
    case Cerfa = 'cerfa';
    case Convention = 'convention';
    case Calendrier = 'calendrier';
    case JustificatifAbsence = 'justificatif_absence';
    case FeuilleEmargement = 'feuille_emargement';
    case Bulletin = 'bulletin';
    case DocumentPedagogique = 'document_pedagogique';
    case Facture = 'facture';
    case DocumentQualite = 'document_qualite';
    case Autre = 'autre';

    /**
     * Pièces pertinentes lors de la phase d'admission.
     * Exclut volontairement les documents des phases suivantes :
     * le CV du maître d'apprentissage, le contrat, le CERFA et la convention
     * relèvent de la contractualisation, pas de l'admission du candidat.
     */
    public static function pourAdmission(): array
    {
        return [
            self::PieceIdentite,
            self::CvCandidat,
            self::DiplomeBulletins,
            self::TestPositionnement,
            self::Autre,
        ];
    }

    /** Construit un tableau value => libellé pour les listes déroulantes Filament. */
    public static function optionsPour(array $cases): array
    {
        return collect($cases)
            ->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])
            ->all();
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::PieceIdentite => "Pièce d'identité",
            self::CvCandidat => 'CV candidat',
            self::CarteVitale => 'Carte Vitale / attestation sécurité sociale',
            self::DiplomeBulletins => 'Diplômes / bulletins',
            self::CvMaitreApprentissage => "CV maître d'apprentissage",
            self::TestPositionnement => 'Test de positionnement',
            self::Contrat => 'Contrat',
            self::Cerfa => 'CERFA',
            self::Convention => 'Convention',
            self::Calendrier => 'Calendrier',
            self::JustificatifAbsence => "Justificatif d'absence",
            self::FeuilleEmargement => "Feuille d'émargement",
            self::Bulletin => 'Bulletin de notes',
            self::DocumentPedagogique => 'Document pédagogique (bulletin, notes…)',
            self::Facture => 'Facture',
            self::DocumentQualite => 'Document qualité',
            self::Autre => 'Autre',
        };
    }
}
