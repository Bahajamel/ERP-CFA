<?php

namespace App\StateMachine;

/**
 * À utiliser dans les enums de statut : fournit canTransitionTo() à partir
 * de la carte des transitions déclarée dans transitions().
 */
trait DefinesTransitions
{
    public function canTransitionTo(HasStateTransitions $target): bool
    {
        return in_array($target, $this->transitions(), true);
    }
}
