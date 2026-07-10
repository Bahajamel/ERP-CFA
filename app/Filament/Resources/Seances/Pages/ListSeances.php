<?php

namespace App\Filament\Resources\Seances\Pages;

use App\Filament\Resources\Seances\SeanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSeances extends ListRecords
{
    protected static string $resource = SeanceResource::class;

    public function getSubheading(): ?string
    {
        return 'Les séances de formation et l\'assiduité : créez une séance, saisissez présences, '
            .'absences et retards. Ces données alimentent la conformité Qualiopi — une absence '
            .'injustifiée déclenche une alerte.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nouvelle séance'),
        ];
    }
}
