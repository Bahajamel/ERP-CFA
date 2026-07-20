<?php

namespace App\Enums;

use App\StateMachine\DefinesTransitions;
use App\StateMachine\HasStateTransitions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Étape « Recherche entreprise » du cycle apprenant : rapprochement d'un
 * candidat accepté par le CFA avec une entreprise (partenaire ou trouvée
 * par le candidat lui-même). « Accepté » ouvre la création du contrat.
 */
enum MatchingStatut: string implements HasColor, HasLabel, HasStateTransitions
{
    use DefinesTransitions;

    case EnRecherche = 'en_recherche';
    case PropositionEnvoyee = 'proposition_envoyee';
    case EntretienEntreprise = 'entretien_entreprise';
    case Accepte = 'accepte';
    case Refuse = 'refuse';
    case Abandonne = 'abandonne';

    public function getLabel(): string
    {
        return match ($this) {
            self::EnRecherche => 'En recherche',
            self::PropositionEnvoyee => 'Proposition envoyée',
            self::EntretienEntreprise => 'Entretien entreprise',
            self::Accepte => 'Accepté',
            self::Refuse => 'Refusé',
            self::Abandonne => 'Abandonné',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::EnRecherche => 'gray',
            self::PropositionEnvoyee => 'info',
            self::EntretienEntreprise => 'warning',
            self::Accepte => 'success',
            self::Refuse => 'danger',
            self::Abandonne => 'gray',
        };
    }

    public function transitions(): array
    {
        return match ($this) {
            self::EnRecherche => [self::PropositionEnvoyee, self::EntretienEntreprise, self::Refuse, self::Abandonne],
            self::PropositionEnvoyee => [self::EntretienEntreprise, self::Accepte, self::Refuse, self::Abandonne],
            self::EntretienEntreprise => [self::Accepte, self::Refuse, self::Abandonne],
            self::Accepte => [self::Abandonne],
            self::Refuse, self::Abandonne => [],
        };
    }
}
