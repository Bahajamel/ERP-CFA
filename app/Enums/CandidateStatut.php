<?php

namespace App\Enums;

use App\StateMachine\DefinesTransitions;
use App\StateMachine\HasStateTransitions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Décision du CFA sur la candidature — première étape du cycle apprenant.
 * Le parcours aval (matching, contrat, OPCO, admission, rupture) est porté
 * par les modules dédiés : le statut candidat ne revient jamais en arrière
 * après une décision finale (Accepté / Refusé).
 */
enum CandidateStatut: string implements HasLabel, HasColor, HasIcon, HasStateTransitions
{
    use DefinesTransitions;

    case EntretienPrevu = 'entretien_prevu';
    case Accepte = 'accepte';
    case Refuse = 'refuse';

    public function getLabel(): string
    {
        return match ($this) {
            self::EntretienPrevu => 'Entretien prévu',
            self::Accepte => 'Accepté',
            self::Refuse => 'Refusé',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::EntretienPrevu => 'warning',
            self::Accepte => 'success',
            self::Refuse => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::EntretienPrevu => 'heroicon-o-calendar-days',
            self::Accepte => 'heroicon-o-check-badge',
            self::Refuse => 'heroicon-o-x-circle',
        };
    }

    /** Les décisions finales sont terminales : aucun retour à « Entretien prévu ». */
    public function transitions(): array
    {
        return match ($this) {
            self::EntretienPrevu => [self::Accepte, self::Refuse],
            self::Accepte => [],
            self::Refuse => [],
        };
    }

    /** Colonnes du pipeline (Kanban), dans l'ordre du parcours. */
    public static function board(): array
    {
        return self::cases();
    }
}
