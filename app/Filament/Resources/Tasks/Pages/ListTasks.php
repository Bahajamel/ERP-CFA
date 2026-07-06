<?php

namespace App\Filament\Resources\Tasks\Pages;

use App\Filament\Resources\Tasks\TaskResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTasks extends ListRecords
{
    protected static string $resource = TaskResource::class;

    public function getSubheading(): ?string
    {
        return 'Votre liste de travail : tâches assignées aux équipes, manuelles ou générées '
            .'automatiquement par l\'ERP (pièce manquante, OPCO bloqué, facture en retard, '
            .'non-conformité Qualiopi…). Priorisez et traitez — rien ne se perd.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
