<?php

namespace App\Policies;

/** Accès au module « Document » : capacités gouvernées par la permission « access_documents ». */
class DocumentPolicy extends ModulePolicy
{
    protected function permission(): string
    {
        return 'access_documents';
    }
}
