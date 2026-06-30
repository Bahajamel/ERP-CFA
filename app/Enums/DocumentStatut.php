<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DocumentStatut: string implements HasLabel, HasColor
{
    case EnAttente = 'en_attente';
    case Recu = 'recu';
    case Expire = 'expire';

    public function getLabel(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente',
            self::Recu => 'Reçu',
            self::Expire => 'Expiré',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::EnAttente => 'warning',
            self::Recu => 'success',
            self::Expire => 'danger',
        };
    }
}
