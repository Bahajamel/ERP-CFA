<?php

namespace App\Filament\Editeur\Resources\Organisations\Pages;

use App\Filament\Editeur\Resources\Organisations\OrganisationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrganisations extends ListRecords
{
    protected static string $resource = OrganisationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nouveau CFA'),
        ];
    }
}
