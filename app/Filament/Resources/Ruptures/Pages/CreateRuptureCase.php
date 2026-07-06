<?php

namespace App\Filament\Resources\Ruptures\Pages;

use App\Filament\Resources\Ruptures\RuptureCaseResource;
use App\Services\RuptureService;
use Filament\Resources\Pages\CreateRecord;

class CreateRuptureCase extends CreateRecord
{
    protected static string $resource = RuptureCaseResource::class;

    /**
     * Ouvrir un dossier n'est pas un simple insert : le contrat passe à
     * « Rompu » et la régularisation OPCO / Finance est tracée. On délègue au
     * service métier (idempotent sur le dossier déjà créé par le formulaire).
     */
    protected function afterCreate(): void
    {
        $this->record->loadMissing('contract');

        if ($this->record->contract !== null) {
            app(RuptureService::class)->ouvrir($this->record->contract);
        }
    }
}
