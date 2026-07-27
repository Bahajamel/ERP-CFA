<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Nature du contrat d'alternance. La priorité de l'ERP est le contrat
 * d'apprentissage (CERFA 10103*14) ; le contrat de professionnalisation est
 * conservé pour l'existant et les besoins futurs.
 */
enum TypeContrat: string implements HasLabel, HasColor
{
    case Apprentissage = 'apprentissage';
    case Professionnalisation = 'professionnalisation';

    public function getLabel(): string
    {
        return match ($this) {
            self::Apprentissage => 'Apprentissage',
            self::Professionnalisation => 'Professionnalisation',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Apprentissage => 'primary',
            self::Professionnalisation => 'info',
        };
    }
}
