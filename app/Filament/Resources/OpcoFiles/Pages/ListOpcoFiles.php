<?php

namespace App\Filament\Resources\OpcoFiles\Pages;

use App\Filament\Resources\OpcoFiles\OpcoFileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOpcoFiles extends ListRecords
{
    protected static string $resource = OpcoFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
