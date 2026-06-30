<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ChecklistItemStatut: string implements HasLabel, HasColor
{
    case Manquante = 'manquante';
    case Presente = 'presente';
    case NonConforme = 'non_conforme';

    public function getLabel(): string
    {
        return match ($this) {
            self::Manquante => 'Manquante',
            self::Presente => 'Présente',
            self::NonConforme => 'Non conforme',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Manquante => 'danger',
            self::Presente => 'success',
            self::NonConforme => 'warning',
        };
    }
}
