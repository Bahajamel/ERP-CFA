<?php

namespace App\Filament\Resources\Opcos\Pages;

use App\Filament\Resources\Opcos\OpcoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOpcos extends ListRecords
{
    protected static string $resource = OpcoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
