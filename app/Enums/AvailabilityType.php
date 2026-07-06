<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Nature d'une période de disponibilité du candidat. */
enum AvailabilityType: string implements HasLabel, HasColor
{
    case Disponible = 'disponible';
    case Indisponible = 'indisponible';

    public function getLabel(): string
    {
        return match ($this) {
            self::Disponible => 'Disponible',
            self::Indisponible => 'Indisponible',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Disponible => 'success',
            self::Indisponible => 'danger',
        };
    }
}
