<?php

namespace App\Policies;

/** Accès au module « Promotion » : capacités gouvernées par la permission « access_formations ». */
class PromotionPolicy extends ModulePolicy
{
    protected function permission(): string
    {
        return 'access_formations';
    }
}
