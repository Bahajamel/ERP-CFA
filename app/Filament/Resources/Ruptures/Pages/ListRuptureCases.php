<?php

namespace App\Filament\Resources\Ruptures\Pages;

use App\Filament\Resources\Ruptures\RuptureCaseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRuptureCases extends ListRecords
{
    protected static string $resource = RuptureCaseResource::class;

    public function getSubheading(): ?string
    {
        return 'Quand une rupture survient, on ne se contente pas de la constater : ce dossier trace le '
            .'motif, déclenche la régularisation OPCO / Finance et pilote l\'accompagnement de '
            .'l\'apprenti vers un nouvel employeur (reclassement). Le plus souvent, on l\'ouvre '
            .'depuis le contrat concerné.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Ouvrir un dossier de rupture'),
        ];
    }
}
