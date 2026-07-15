<?php

namespace App\Policies;

/** Accès au module « Entretien » : capacités gouvernées par la permission « access_candidates ». */
class EntretienPolicy extends ModulePolicy
{
    protected function permission(): string
    {
        return 'access_candidates';
    }
}
