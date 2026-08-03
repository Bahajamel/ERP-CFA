<?php

namespace App\Filament\Resources\Seances\Pages;

use App\Filament\Resources\Seances\SeanceResource;
use App\Filament\Widgets\SeancesStatsOverview;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSeances extends ListRecords
{
    protected static string $resource = SeanceResource::class;

    public function getSubheading(): ?string
    {
        return 'Planifiez vos séances, suivez les présences et générez les feuilles d\'émargement '
            .'conformes Qualiopi. Une absence injustifiée déclenche automatiquement une tâche de suivi.';
    }

    /** Cartes KPI en tête de page. */
    protected function getHeaderWidgets(): array
    {
        return [
            SeancesStatsOverview::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nouvelle séance'),
        ];
    }
}
