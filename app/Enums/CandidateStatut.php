<?php

namespace App\Enums;

use App\StateMachine\DefinesTransitions;
use App\StateMachine\HasStateTransitions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum CandidateStatut: string implements HasLabel, HasColor, HasIcon, HasStateTransitions
{
    use DefinesTransitions;

    case Incomplet = 'incomplet';
    case Complet = 'complet';
    case EnRechercheEntreprise = 'en_recherche_entreprise';
    case ContratSigne = 'contrat_signe';
    case Rupture = 'rupture';

    public function getLabel(): string
    {
        return match ($this) {
            self::Incomplet => 'Dossier incomplet',
            self::Complet => 'Dossier complet',
            self::EnRechercheEntreprise => "En recherche d'entreprise",
            self::ContratSigne => 'Contrat signé',
            self::Rupture => 'Rupture',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Incomplet => 'gray',
            self::Complet => 'info',
            self::EnRechercheEntreprise => 'warning',
            self::ContratSigne => 'success',
            self::Rupture => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Incomplet => 'heroicon-o-document-minus',
            self::Complet => 'heroicon-o-document-check',
            self::EnRechercheEntreprise => 'heroicon-o-magnifying-glass',
            self::ContratSigne => 'heroicon-o-check-badge',
            self::Rupture => 'heroicon-o-x-circle',
        };
    }

    public function transitions(): array
    {
        return match ($this) {
            self::Incomplet => [self::Complet, self::EnRechercheEntreprise],
            self::Complet => [self::EnRechercheEntreprise, self::Incomplet],
            self::EnRechercheEntreprise => [self::ContratSigne, self::Rupture],
            self::ContratSigne => [self::Rupture],
            self::Rupture => [],
        };
    }

    /** Colonnes du pipeline (Kanban), dans l'ordre du parcours. */
    public static function board(): array
    {
        return self::cases();
    }
}
