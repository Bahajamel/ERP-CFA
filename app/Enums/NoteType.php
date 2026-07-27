<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Nature d'une note interne (P0-03-5) : simple note, compte rendu de rendez-vous,
 * incident à traiter ou mesure de satisfaction. Sert au suivi relation entreprise.
 */
enum NoteType: string implements HasColor, HasLabel
{
    case Note = 'note';
    case CompteRendu = 'compte_rendu';
    case Incident = 'incident';
    case Satisfaction = 'satisfaction';

    public function getLabel(): string
    {
        return match ($this) {
            self::Note => 'Note',
            self::CompteRendu => 'Compte rendu',
            self::Incident => 'Incident',
            self::Satisfaction => 'Satisfaction',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Note => 'gray',
            self::CompteRendu => 'info',
            self::Incident => 'danger',
            self::Satisfaction => 'success',
        };
    }
}
