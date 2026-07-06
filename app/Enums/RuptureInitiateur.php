<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Partie à l'origine de la rupture du contrat d'apprentissage. */
enum RuptureInitiateur: string implements HasLabel, HasColor
{
    case Apprenti = 'apprenti';
    case Employeur = 'employeur';
    case CommunAccord = 'commun_accord';
    case Cfa = 'cfa';

    public function getLabel(): string
    {
        return match ($this) {
            self::Apprenti => "L'apprenti",
            self::Employeur => "L'employeur",
            self::CommunAccord => "Commun accord",
            self::Cfa => 'Le CFA',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Apprenti => 'warning',
            self::Employeur => 'warning',
            self::CommunAccord => 'info',
            self::Cfa => 'danger',
        };
    }
}
