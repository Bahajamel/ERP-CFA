<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TaskPriorite: string implements HasLabel, HasColor
{
    case Basse = 'basse';
    case Normale = 'normale';
    case Haute = 'haute';
    case Urgente = 'urgente';

    public function getLabel(): string
    {
        return match ($this) {
            self::Basse => 'Basse',
            self::Normale => 'Normale',
            self::Haute => 'Haute',
            self::Urgente => 'Urgente',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Basse => 'gray',
            self::Normale => 'info',
            self::Haute => 'warning',
            self::Urgente => 'danger',
        };
    }
}
