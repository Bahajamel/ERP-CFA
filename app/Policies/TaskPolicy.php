<?php

namespace App\Policies;

/** Accès au module « Task » : capacités gouvernées par la permission « access_tasks ». */
class TaskPolicy extends ModulePolicy
{
    protected function permission(): string
    {
        return 'access_tasks';
    }
}
