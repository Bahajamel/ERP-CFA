<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ContractSignatureStatut: string implements HasColor, HasLabel
{
    case NonSigne = 'non_signe';
    case Envoye = 'envoye';
    case Signe = 'signe';

    public function getLabel(): string
    {
        return match ($this) {
            self::NonSigne => 'Non signé',
            self::Envoye => 'Envoyé pour signature',
            self::Signe => 'Signé',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NonSigne => 'gray',
            self::Envoye => 'warning',
            self::Signe => 'success',
        };
    }
}
