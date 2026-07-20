<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CompanyStatut: string implements HasColor, HasLabel
{
    case Prospect = 'prospect';
    case Partenaire = 'partenaire';
    case Active = 'active';
    case Inactive = 'inactive';

    public function getLabel(): string
    {
        return match ($this) {
            self::Prospect => 'Prospect',
            self::Partenaire => 'Partenaire',
            self::Active => 'Active',
            self::Inactive => 'Inactive',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Prospect => 'gray',
            self::Partenaire => 'info',
            self::Active => 'success',
            self::Inactive => 'danger',
        };
    }
}
