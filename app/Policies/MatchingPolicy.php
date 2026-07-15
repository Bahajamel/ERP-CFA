<?php

namespace App\Policies;

/** Accès au module « Matching » : capacités gouvernées par la permission « access_matching ». */
class MatchingPolicy extends ModulePolicy
{
    protected function permission(): string
    {
        return 'access_matching';
    }
}
