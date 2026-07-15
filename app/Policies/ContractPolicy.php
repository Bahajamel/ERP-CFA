<?php

namespace App\Policies;

/** Accès au module « Contract » : capacités gouvernées par la permission « access_contracts ». */
class ContractPolicy extends ModulePolicy
{
    protected function permission(): string
    {
        return 'access_contracts';
    }
}
