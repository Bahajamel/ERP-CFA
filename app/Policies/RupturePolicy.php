<?php

namespace App\Policies;

/** Accès au module « Rupture » : capacités gouvernées par la permission « access_ruptures ». */
class RupturePolicy extends ModulePolicy
{
    protected function permission(): string
    {
        return 'access_ruptures';
    }
}
