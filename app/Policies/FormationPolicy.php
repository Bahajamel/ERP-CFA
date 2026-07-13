<?php

namespace App\Policies;

/** Accès au module « Formation » : capacités gouvernées par la permission « access_formations ». */
class FormationPolicy extends ModulePolicy
{
    protected function permission(): string
    {
        return 'access_formations';
    }
}
