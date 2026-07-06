<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Motifs de rupture d'un contrat d'apprentissage (Code du travail, art. L6222-18
 * et suivants). Les cases couvrent les cas réglementaires les plus fréquents ;
 * le détail libre (motif_detail) précise la situation.
 */
enum RuptureMotif: string implements HasLabel, HasColor
{
    case PeriodeEssai = 'periode_essai';
    case CommunAccord = 'commun_accord';
    case InitiativeApprenti = 'initiative_apprenti';
    case InitiativeEmployeur = 'initiative_employeur';
    case Abandon = 'abandon';
    case ObtentionDiplome = 'obtention_diplome';
    case ExclusionCfa = 'exclusion_cfa';
    case LiquidationEntreprise = 'liquidation_entreprise';
    case ForceMajeureInaptitude = 'force_majeure_inaptitude';
    case Autre = 'autre';

    public function getLabel(): string
    {
        return match ($this) {
            self::PeriodeEssai => "Rupture pendant les 45 premiers jours",
            self::CommunAccord => "Résiliation d'un commun accord",
            self::InitiativeApprenti => "À l'initiative de l'apprenti (démission)",
            self::InitiativeEmployeur => "À l'initiative de l'employeur (licenciement)",
            self::Abandon => "Abandon de poste de l'apprenti",
            self::ObtentionDiplome => "Rupture anticipée à l'obtention du diplôme",
            self::ExclusionCfa => "Exclusion définitive du CFA",
            self::LiquidationEntreprise => "Liquidation / cessation d'activité de l'entreprise",
            self::ForceMajeureInaptitude => "Force majeure / inaptitude",
            self::Autre => "Autre motif",
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PeriodeEssai => 'gray',
            self::CommunAccord => 'info',
            self::ObtentionDiplome => 'success',
            self::InitiativeApprenti, self::InitiativeEmployeur => 'warning',
            self::Abandon, self::ExclusionCfa, self::LiquidationEntreprise, self::ForceMajeureInaptitude => 'danger',
            self::Autre => 'gray',
        };
    }
}
