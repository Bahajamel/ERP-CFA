<?php

namespace App\StateMachine;

use Filament\Support\Contracts\HasLabel;

/**
 * Contrat des enums de statut participant à une machine à états.
 * Implémenté par CandidateStatut, NeedStatut, MatchingStatut, etc.
 *
 * Étend HasLabel : tout état sait fournir son libellé ({@see getLabel()}),
 * utilisé par la machine à états pour les messages de transition.
 */
interface HasStateTransitions extends HasLabel
{
    /**
     * États cibles autorisés depuis l'état courant (transitions structurelles).
     *
     * @return array<int, static>
     */
    public function transitions(): array;

    /** L'état courant peut-il transiter vers $target ? (structure uniquement) */
    public function canTransitionTo(HasStateTransitions $target): bool;
}
