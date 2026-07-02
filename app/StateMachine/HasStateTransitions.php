<?php

namespace App\StateMachine;

use BackedEnum;
use Filament\Support\Contracts\HasLabel;

/**
 * Contrat des enums de statut participant à une machine à états.
 * Implémenté par CandidateStatut, NeedStatut, MatchingStatut, etc.
 *
 * Étend BackedEnum (seuls des enums « backed » l'implémentent) et HasLabel
 * (tout état fournit son libellé, utilisé dans les messages de transition).
 */
interface HasStateTransitions extends BackedEnum, HasLabel
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
