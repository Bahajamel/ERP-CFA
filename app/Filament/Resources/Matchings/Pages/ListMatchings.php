<?php

namespace App\Filament\Resources\Matchings\Pages;

use App\Filament\Resources\Matchings\MatchingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMatchings extends ListRecords
{
    protected static string $resource = MatchingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
