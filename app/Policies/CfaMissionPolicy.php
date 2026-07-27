<?php

namespace App\Policies;

/** Accès au module « CfaMission » : capacités gouvernées par la permission « access_documents ». */
class CfaMissionPolicy extends ModulePolicy
{
    protected function permission(): string
    {
        return 'access_documents';
    }
}
