<?php

namespace App\Enums;

use App\StateMachine\DefinesTransitions;
use App\StateMachine\HasStateTransitions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MatchingStatut: string implements HasLabel, HasColor, HasStateTransitions
{
    use DefinesTransitions;

    case Propose = 'propose';
    case CvEnvoye = 'cv_envoye';
    case EntretienPrevu = 'entretien_prevu';
    case AttenteRetour = 'attente_retour';
    case Accepte = 'accepte';
    case RefuseEntreprise = 'refuse_entreprise';
    case RefuseCandidat = 'refuse_candidat';
    case Abandonne = 'abandonne';

    public function getLabel(): string
    {
        return match ($this) {
            self::Propose => 'Proposé',
            self::CvEnvoye => 'CV envoyé',
            self::EntretienPrevu => 'Entretien prévu',
            self::AttenteRetour => 'En attente de retour',
            self::Accepte => 'Accepté',
            self::RefuseEntreprise => "Refusé par l'entreprise",
            self::RefuseCandidat => 'Refusé par le candidat',
            self::Abandonne => 'Abandonné',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Propose => 'gray',
            self::CvEnvoye, self::EntretienPrevu, self::AttenteRetour => 'warning',
            self::Accepte => 'success',
            self::RefuseEntreprise, self::RefuseCandidat => 'danger',
            self::Abandonne => 'gray',
        };
    }

    public function transitions(): array
    {
        return match ($this) {
            self::Propose => [self::CvEnvoye, self::RefuseCandidat, self::Abandonne],
            self::CvEnvoye => [self::EntretienPrevu, self::AttenteRetour, self::RefuseEntreprise, self::RefuseCandidat, self::Abandonne],
            self::EntretienPrevu => [self::AttenteRetour, self::Accepte, self::RefuseEntreprise, self::RefuseCandidat, self::Abandonne],
            self::AttenteRetour => [self::Accepte, self::RefuseEntreprise, self::RefuseCandidat, self::Abandonne],
            self::Accepte => [self::Abandonne],
            self::RefuseEntreprise, self::RefuseCandidat, self::Abandonne => [],
        };
    }
}
