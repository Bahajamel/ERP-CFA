<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DocumentType: string implements HasLabel
{
    case PieceIdentite = 'piece_identite';
    case CvCandidat = 'cv_candidat';
    case DiplomeBulletins = 'diplome_bulletins';
    case CvMaitreApprentissage = 'cv_maitre_apprentissage';
    case TestPositionnement = 'test_positionnement';
    case Contrat = 'contrat';
    case Cerfa = 'cerfa';
    case Convention = 'convention';
    case Calendrier = 'calendrier';
    case JustificatifAbsence = 'justificatif_absence';
    case PreuveServiceFait = 'preuve_service_fait';
    case Facture = 'facture';
    case DocumentQualite = 'document_qualite';
    case Autre = 'autre';

    public function getLabel(): string
    {
        return match ($this) {
            self::PieceIdentite => "Pièce d'identité",
            self::CvCandidat => 'CV candidat',
            self::DiplomeBulletins => 'Diplômes / bulletins',
            self::CvMaitreApprentissage => "CV maître d'apprentissage",
            self::TestPositionnement => 'Test de positionnement',
            self::Contrat => 'Contrat',
            self::Cerfa => 'CERFA',
            self::Convention => 'Convention',
            self::Calendrier => 'Calendrier',
            self::JustificatifAbsence => "Justificatif d'absence",
            self::PreuveServiceFait => 'Preuve de service fait',
            self::Facture => 'Facture',
            self::DocumentQualite => 'Document qualité',
            self::Autre => 'Autre',
        };
    }
}
