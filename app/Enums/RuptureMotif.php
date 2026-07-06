<?php

namespace App\Enums;

<<<<<<< HEAD
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
=======
use Filament\Support\Contracts\HasLabel;

enum RuptureMotif: string implements HasLabel
{
    case Demission = 'demission';
    case Licenciement = 'licenciement';
    case CommunAccord = 'commun_accord';
    case Abandon = 'abandon';
    case Inaptitude = 'inaptitude';
    case EchecPeriodeEssai = 'echec_periode_essai';
>>>>>>> a2c0d03f96d990843d553f1798403e8b8d5fb951
    case Autre = 'autre';

    public function getLabel(): string
    {
        return match ($this) {
<<<<<<< HEAD
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
=======
            self::Demission => 'Démission de l\'apprenti',
            self::Licenciement => 'Licenciement',
            self::CommunAccord => 'Rupture d\'un commun accord',
            self::Abandon => 'Abandon',
            self::Inaptitude => 'Inaptitude',
            self::EchecPeriodeEssai => 'Échec en période d\'essai',
            self::Autre => 'Autre',
>>>>>>> a2c0d03f96d990843d553f1798403e8b8d5fb951
        };
    }
}
