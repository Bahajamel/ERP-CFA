<?php

namespace App\Filament\Editeur\Resources\Organisations\Pages;

use App\Filament\Editeur\Resources\Organisations\OrganisationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrganisation extends CreateRecord
{
    protected static string $resource = OrganisationResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
