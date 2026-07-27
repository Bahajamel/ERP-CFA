<?php

namespace App\Filament\Resources\Formations\Pages;

use App\Filament\Resources\Formations\FormationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFormations extends ListRecords
{
    protected static string $resource = FormationResource::class;

    public function getSubheading(): ?string
    {
        return 'Le catalogue des formations du CFA (intitulé, code RNCP, niveau). C\'est le référentiel '
            .'partagé : candidats, besoins et contrats s\'y rattachent. Vérifiez la validité RNCP en '
            .'ligne pour sécuriser l\'éligibilité au financement.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
