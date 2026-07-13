<?php

namespace App\Policies;

/** Accès au module « Candidate » : capacités gouvernées par la permission « access_candidates ». */
class CandidatePolicy extends ModulePolicy
{
    protected function permission(): string
    {
        return 'access_candidates';
    }
}
