<?php

namespace App\Policies;

/** Accès au module « Admission » : capacités gouvernées par la permission « access_admissions ». */
class AdmissionPolicy extends ModulePolicy
{
    protected function permission(): string
    {
        return 'access_admissions';
    }
}
