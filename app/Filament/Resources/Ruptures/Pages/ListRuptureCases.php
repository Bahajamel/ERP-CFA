<?php

namespace App\Filament\Resources\Ruptures\Pages;

use App\Filament\Resources\Ruptures\RuptureCaseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRuptureCases extends ListRecords
{
    protected static string $resource = RuptureCaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Ouvrir un dossier de rupture'),
        ];
    }
}
