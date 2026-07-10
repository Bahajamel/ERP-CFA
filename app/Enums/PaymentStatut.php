<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatut: string implements HasLabel, HasColor
{
    case Attendu = 'attendu';
    case Verse = 'verse';
    case EnRetard = 'en_retard';

    public function getLabel(): string
    {
        return match ($this) {
            self::Attendu => 'Attendu',
            self::Verse => 'Versé',
            self::EnRetard => 'En retard',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Attendu => 'warning',
            self::Verse => 'success',
            self::EnRetard => 'danger',
        };
    }
}
