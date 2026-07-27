<?php

namespace App\Policies;

/** Accès au module « User » : capacités gouvernées par la permission « access_users ». */
class UserPolicy extends ModulePolicy
{
    protected function permission(): string
    {
        return 'access_users';
    }
}
