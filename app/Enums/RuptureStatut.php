<?php

namespace App\Enums;

use App\StateMachine\DefinesTransitions;
use App\StateMachine\HasStateTransitions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RuptureStatut: string implements HasLabel, HasColor, HasStateTransitions
{
    use DefinesTransitions;

    case Ouverte = 'ouverte';
    case EnAccompagnement = 'en_accompagnement';
    case RechercheEmployeur = 'recherche_employeur';
    case Reclasse = 'reclasse';
    case Cloturee = 'cloturee';

    public function getLabel(): string
    {
        return match ($this) {
            self::Ouverte => 'Ouverte',
            self::EnAccompagnement => 'En accompagnement',
            self::RechercheEmployeur => 'Recherche employeur',
            self::Reclasse => 'Reclassé',
            self::Cloturee => 'Clôturée',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Ouverte => 'warning',
            self::EnAccompagnement => 'info',
            self::RechercheEmployeur => 'info',
            self::Reclasse => 'success',
            self::Cloturee => 'gray',
        };
    }

    public function transitions(): array
    {
        return match ($this) {
            self::Ouverte => [self::EnAccompagnement, self::Cloturee],
            self::EnAccompagnement => [self::RechercheEmployeur, self::Reclasse, self::Cloturee],
            self::RechercheEmployeur => [self::Reclasse, self::Cloturee],
            self::Reclasse => [self::Cloturee],
            self::Cloturee => [],
        };
    }
}
