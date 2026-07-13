<?php

namespace App\Policies;

/** Accès au module « Evaluation » : capacités gouvernées par la permission « access_attendance ». */
class EvaluationPolicy extends ModulePolicy
{
    protected function permission(): string
    {
        return 'access_attendance';
    }
}
