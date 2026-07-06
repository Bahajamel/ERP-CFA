<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RuptureMotif: string implements HasLabel
{
    case Demission = 'demission';
    case Licenciement = 'licenciement';
    case CommunAccord = 'commun_accord';
    case Abandon = 'abandon';
    case Inaptitude = 'inaptitude';
    case EchecPeriodeEssai = 'echec_periode_essai';
    case Autre = 'autre';

    public function getLabel(): string
    {
        return match ($this) {
            self::Demission => 'Démission de l\'apprenti',
            self::Licenciement => 'Licenciement',
            self::CommunAccord => 'Rupture d\'un commun accord',
            self::Abandon => 'Abandon',
            self::Inaptitude => 'Inaptitude',
            self::EchecPeriodeEssai => 'Échec en période d\'essai',
            self::Autre => 'Autre',
        };
    }
}
