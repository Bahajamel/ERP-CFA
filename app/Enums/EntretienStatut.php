<?php

namespace App\Enums;

use App\StateMachine\DefinesTransitions;
use App\StateMachine\HasStateTransitions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Suivi d'un entretien candidat (section Entretiens du cycle apprenant).
 * « Planifié » exige un créneau complet (date + heures) et fait passer le
 * candidat à « Entretien prévu » ; « Réalisé » ouvre la décision finale
 * (accepter / refuser le candidat).
 */
enum EntretienStatut: string implements HasLabel, HasColor, HasIcon, HasStateTransitions
{
    use DefinesTransitions;

    case APlanifier = 'a_planifier';
    case Planifie = 'planifie';
    case Realise = 'realise';
    case Annule = 'annule';
    case Absent = 'absent';
    case AReprogrammer = 'a_reprogrammer';

    public function getLabel(): string
    {
        return match ($this) {
            self::APlanifier => 'À planifier',
            self::Planifie => 'Planifié',
            self::Realise => 'Réalisé',
            self::Annule => 'Annulé',
            self::Absent => 'Absent',
            self::AReprogrammer => 'À reprogrammer',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::APlanifier => 'gray',
            self::Planifie => 'info',
            self::Realise => 'success',
            self::Annule => 'danger',
            self::Absent => 'warning',
            self::AReprogrammer => 'warning',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::APlanifier => 'heroicon-o-clock',
            self::Planifie => 'heroicon-o-calendar-days',
            self::Realise => 'heroicon-o-check-circle',
            self::Annule => 'heroicon-o-x-circle',
            self::Absent => 'heroicon-o-user-minus',
            self::AReprogrammer => 'heroicon-o-arrow-path',
        };
    }

    public function transitions(): array
    {
        return match ($this) {
            self::APlanifier => [self::Planifie, self::Annule],
            self::Planifie => [self::Realise, self::Absent, self::AReprogrammer, self::Annule],
            self::AReprogrammer => [self::Planifie, self::Annule],
            self::Absent => [self::AReprogrammer, self::Annule],
            // Réalisé et Annulé sont terminaux.
            self::Realise, self::Annule => [],
        };
    }

    /** L'entretien occupe-t-il encore un créneau à venir (agenda) ? */
    public function estActif(): bool
    {
        return in_array($this, [self::APlanifier, self::Planifie, self::AReprogrammer, self::Absent], true);
    }
}
