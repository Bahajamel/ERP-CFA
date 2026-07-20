<?php

namespace App\Filament\Resources\CustomTables\Pages;

use App\Filament\Resources\CustomTables\CustomTableResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomTables extends ListRecords
{
    protected static string $resource = CustomTableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Créer un tableau'),
        ];
    }
}
