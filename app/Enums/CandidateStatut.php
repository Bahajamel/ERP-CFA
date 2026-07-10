<?php

namespace App\Enums;

use App\StateMachine\DefinesTransitions;
use App\StateMachine\HasStateTransitions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Décision du CFA sur la candidature — première étape du cycle apprenant.
 *
 * Tout nouveau candidat démarre à « Entretien à planifier » ; « Entretien
 * prévu » est piloté par la section Entretiens (un vrai créneau planifié).
 * Le parcours aval (matching, contrat, OPCO, admission, rupture) est porté
 * par les modules dédiés : le statut candidat ne revient jamais en arrière
 * après une décision finale (Accepté / Refusé).
 */
enum CandidateStatut: string implements HasLabel, HasColor, HasIcon, HasStateTransitions
{
    use DefinesTransitions;

    case EntretienAPlanifier = 'entretien_a_planifier';
    case EntretienPrevu = 'entretien_prevu';
    case EntretienRealise = 'entretien_realise';
    case Accepte = 'accepte';
    case Refuse = 'refuse';

    public function getLabel(): string
    {
        return match ($this) {
            self::EntretienAPlanifier => 'Entretien à planifier',
            self::EntretienPrevu => 'Entretien prévu',
            self::EntretienRealise => 'Entretien réalisé',
            self::Accepte => 'Accepté',
            self::Refuse => 'Refusé',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::EntretienAPlanifier => 'gray',
            self::EntretienPrevu => 'warning',
            self::EntretienRealise => 'info',
            self::Accepte => 'success',
            self::Refuse => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::EntretienAPlanifier => 'heroicon-o-clock',
            self::EntretienPrevu => 'heroicon-o-calendar-days',
            self::EntretienRealise => 'heroicon-o-clipboard-document-check',
            self::Accepte => 'heroicon-o-check-badge',
            self::Refuse => 'heroicon-o-x-circle',
        };
    }

    /**
     * Les décisions finales sont terminales : aucun retour aux statuts
     * d'entretien. « Entretien prévu » peut revenir à « Entretien à
     * planifier » (entretien annulé / à reprogrammer). « Entretien réalisé »
     * (l'entretien a eu lieu) n'ouvre plus que la décision Accepté / Refusé.
     * « Accepté » reste atteignable en amont pour l'exception administrateur.
     */
    public function transitions(): array
    {
        return match ($this) {
            self::EntretienAPlanifier => [self::EntretienPrevu, self::Accepte, self::Refuse],
            self::EntretienPrevu => [self::EntretienAPlanifier, self::EntretienRealise, self::Accepte, self::Refuse],
            self::EntretienRealise => [self::Accepte, self::Refuse],
            self::Accepte => [],
            self::Refuse => [],
        };
    }

    /** Statuts d'entretien (avant décision finale). */
    public static function statutsEntretien(): array
    {
        return [self::EntretienAPlanifier, self::EntretienPrevu, self::EntretienRealise];
    }

    /** La décision finale (Accepté / Refusé) est-elle prise ? */
    public function estFinal(): bool
    {
        return in_array($this, [self::Accepte, self::Refuse], true);
    }

    /** Colonnes du pipeline (Kanban), dans l'ordre du parcours. */
    public static function board(): array
    {
        return self::cases();
    }
}
