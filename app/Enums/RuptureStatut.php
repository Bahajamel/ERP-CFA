<?php

namespace App\Enums;

use App\StateMachine\DefinesTransitions;
use App\StateMachine\HasStateTransitions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

<<<<<<< HEAD
/**
 * Cycle de vie d'un dossier de rupture : de l'ouverture à la clôture, en passant
 * par l'accompagnement de l'apprenti et son éventuel reclassement (nouvel
 * employeur). Machine à états : on ne peut pas clore avant d'avoir ouvert.
 */
=======
>>>>>>> a2c0d03f96d990843d553f1798403e8b8d5fb951
enum RuptureStatut: string implements HasLabel, HasColor, HasStateTransitions
{
    use DefinesTransitions;

<<<<<<< HEAD
    case Ouvert = 'ouvert';
    case EnAccompagnement = 'en_accompagnement';
    case Reclasse = 'reclasse';
    case Clos = 'clos';
=======
    case Ouverte = 'ouverte';
    case EnAccompagnement = 'en_accompagnement';
    case RechercheEmployeur = 'recherche_employeur';
    case Reclasse = 'reclasse';
    case Cloturee = 'cloturee';
>>>>>>> a2c0d03f96d990843d553f1798403e8b8d5fb951

    public function getLabel(): string
    {
        return match ($this) {
<<<<<<< HEAD
            self::Ouvert => 'Ouvert',
            self::EnAccompagnement => 'Accompagnement en cours',
            self::Reclasse => 'Reclassé (nouvel employeur)',
            self::Clos => 'Clos',
=======
            self::Ouverte => 'Ouverte',
            self::EnAccompagnement => 'En accompagnement',
            self::RechercheEmployeur => 'Recherche employeur',
            self::Reclasse => 'Reclassé',
            self::Cloturee => 'Clôturée',
>>>>>>> a2c0d03f96d990843d553f1798403e8b8d5fb951
        };
    }

    public function getColor(): string
    {
        return match ($this) {
<<<<<<< HEAD
            self::Ouvert => 'danger',
            self::EnAccompagnement => 'warning',
            self::Reclasse => 'success',
            self::Clos => 'gray',
=======
            self::Ouverte => 'warning',
            self::EnAccompagnement => 'info',
            self::RechercheEmployeur => 'info',
            self::Reclasse => 'success',
            self::Cloturee => 'gray',
>>>>>>> a2c0d03f96d990843d553f1798403e8b8d5fb951
        };
    }

    public function transitions(): array
    {
        return match ($this) {
<<<<<<< HEAD
            self::Ouvert => [self::EnAccompagnement, self::Clos],
            self::EnAccompagnement => [self::Reclasse, self::Clos],
            self::Reclasse => [self::Clos],
            self::Clos => [],
        };
    }

    /** Dossiers encore actifs (non clos). */
    public static function ouverts(): array
    {
        return [self::Ouvert->value, self::EnAccompagnement->value, self::Reclasse->value];
    }
=======
            self::Ouverte => [self::EnAccompagnement, self::Cloturee],
            self::EnAccompagnement => [self::RechercheEmployeur, self::Reclasse, self::Cloturee],
            self::RechercheEmployeur => [self::Reclasse, self::Cloturee],
            self::Reclasse => [self::Cloturee],
            self::Cloturee => [],
        };
    }
>>>>>>> a2c0d03f96d990843d553f1798403e8b8d5fb951
}
