<?php

namespace App\Policies;

/** Accès au module « Seance » : capacités gouvernées par la permission « access_attendance ». */
class SeancePolicy extends ModulePolicy
{
    protected function permission(): string
    {
        return 'access_attendance';
    }
}
