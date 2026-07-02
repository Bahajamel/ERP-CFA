<?php

namespace App\Filament\Resources\Seances\Pages;

use App\Filament\Resources\Seances\SeanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSeances extends ListRecords
{
    protected static string $resource = SeanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nouvelle séance'),
        ];
    }
}
