<?php

namespace App\Enums;

use App\StateMachine\DefinesTransitions;
use App\StateMachine\HasStateTransitions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Cycle de vie d'un dossier de rupture : de l'ouverture à la clôture, en passant
 * par l'accompagnement de l'apprenti et son éventuel reclassement (nouvel
 * employeur). Machine à états : on ne peut pas clore avant d'avoir ouvert.
 */
enum RuptureStatut: string implements HasLabel, HasColor, HasStateTransitions
{
    use DefinesTransitions;

    case Ouvert = 'ouvert';
    case EnAccompagnement = 'en_accompagnement';
    case Reclasse = 'reclasse';
    case Clos = 'clos';

    public function getLabel(): string
    {
        return match ($this) {
            self::Ouvert => 'Ouvert',
            self::EnAccompagnement => 'Accompagnement en cours',
            self::Reclasse => 'Reclassé (nouvel employeur)',
            self::Clos => 'Clos',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Ouvert => 'danger',
            self::EnAccompagnement => 'warning',
            self::Reclasse => 'success',
            self::Clos => 'gray',
        };
    }

    public function transitions(): array
    {
        return match ($this) {
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
}
