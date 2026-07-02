<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Origine d'un document dans la GED. */
enum DocumentSource: string implements HasLabel, HasColor
{
    case Manuel = 'manuel';
    case LivretRs = 'livretrs';

    public function getLabel(): string
    {
        return match ($this) {
            self::Manuel => 'Déposé manuellement',
            self::LivretRs => 'Généré par LivretRS',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Manuel => 'gray',
            self::LivretRs => 'info',
        };
    }
}
