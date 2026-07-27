<?php

namespace App\Filament\Editeur\Resources\Organisations\Pages;

use App\Filament\Editeur\Resources\Organisations\OrganisationResource;
use App\Qualiopi\ReferentielQualiopi;
use Filament\Resources\Pages\CreateRecord;

class CreateOrganisation extends CreateRecord
{
    protected static string $resource = OrganisationResource::class;

    /**
     * Provisionne le référentiel Qualiopi du nouveau CFA (32 indicateurs, état
     * vierge) : ceux-ci sont cloisonnés par CFA, un centre créé à la main doit
     * donc recevoir les siens comme un CFA ouvert en essai.
     */
    protected function afterCreate(): void
    {
        ReferentielQualiopi::provisionner($this->record->id);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
