<?php

namespace App\StateMachine;

/**
 * Contrat des enums de statut participant à une machine à états.
 * Implémenté par CandidateStatut, NeedStatut, MatchingStatut, etc.
 */
interface HasStateTransitions
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
