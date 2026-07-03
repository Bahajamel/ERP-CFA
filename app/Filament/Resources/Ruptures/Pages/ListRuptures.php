<?php

namespace App\Filament\Resources\Ruptures\Pages;

use App\Filament\Resources\Ruptures\RuptureResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRuptures extends ListRecords
{
    protected static string $resource = RuptureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Ouvrir une rupture'),
        ];
    }
}
