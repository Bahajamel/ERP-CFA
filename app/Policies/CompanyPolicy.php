<?php

namespace App\Policies;

/** Accès au module « Company » : capacités gouvernées par la permission « access_companies ». */
class CompanyPolicy extends ModulePolicy
{
    protected function permission(): string
    {
        return 'access_companies';
    }
}
