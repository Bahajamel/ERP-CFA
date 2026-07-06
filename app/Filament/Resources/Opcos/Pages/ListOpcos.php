<?php

namespace App\Filament\Resources\Opcos\Pages;

use App\Filament\Resources\Opcos\OpcoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOpcos extends ListRecords
{
    protected static string $resource = OpcoResource::class;

    public function getSubheading(): ?string
    {
        return 'Le référentiel des OPCO (opérateurs de compétences) qui financent l\'apprentissage. '
            .'Chaque entreprise est rattachée à son OPCO ; c\'est lui qui traite les dossiers de '
            .'financement des contrats.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
