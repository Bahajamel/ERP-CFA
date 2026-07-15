<?php

namespace App\Filament\Editeur\Resources\Organisations\Pages;

use App\Filament\Editeur\Resources\Organisations\OrganisationResource;
use Filament\Resources\Pages\EditRecord;

class EditOrganisation extends EditRecord
{
    protected static string $resource = OrganisationResource::class;

    // Pas de DeleteAction : supprimer un CFA emporterait ses données (dont des
    // pièces à NIR). La suspension, réversible, est la seule sortie proposée.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
