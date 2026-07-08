<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/** Mode de passation d'un entretien candidat. */
enum EntretienMode: string implements HasLabel, HasIcon
{
    case Presentiel = 'presentiel';
    case Telephone = 'telephone';
    case Visio = 'visio';

    public function getLabel(): string
    {
        return match ($this) {
            self::Presentiel => 'Présentiel',
            self::Telephone => 'Téléphone',
            self::Visio => 'Visio',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Presentiel => 'heroicon-o-building-office',
            self::Telephone => 'heroicon-o-phone',
            self::Visio => 'heroicon-o-video-camera',
        };
    }
}
