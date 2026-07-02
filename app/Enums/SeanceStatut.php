<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Statut d'une séance de formation (EPIC-14 ; « Validée » = base du service fait). */
enum SeanceStatut: string implements HasColor, HasLabel
{
    case Planifiee = 'planifiee';
    case Validee = 'validee';
    case Annulee = 'annulee';

    public function getLabel(): string
    {
        return match ($this) {
            self::Planifiee => 'Planifiée',
            self::Validee => 'Validée',
            self::Annulee => 'Annulée',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Planifiee => 'warning',
            self::Validee => 'success',
            self::Annulee => 'danger',
        };
    }
}
