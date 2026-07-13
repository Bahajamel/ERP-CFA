<?php

namespace App\Policies;

/** Accès au module « OpcoFile » : capacités gouvernées par la permission « access_opco ». */
class OpcoFilePolicy extends ModulePolicy
{
    protected function permission(): string
    {
        return 'access_opco';
    }
}
