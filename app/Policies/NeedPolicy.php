<?php

namespace App\Policies;

/** Accès au module « Need » : capacités gouvernées par la permission « access_needs ». */
class NeedPolicy extends ModulePolicy
{
    protected function permission(): string
    {
        return 'access_needs';
    }
}
