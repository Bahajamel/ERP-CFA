<?php

namespace App\Policies;

/** Accès au module « QualiopiIndicator » : capacités gouvernées par la permission « access_quality ». */
class QualiopiIndicatorPolicy extends ModulePolicy
{
    protected function permission(): string
    {
        return 'access_quality';
    }
}
